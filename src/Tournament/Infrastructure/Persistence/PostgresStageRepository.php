<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use stdClass;
use Tournaments\Tournament\Domain\Exception\StageNotFound;
use Tournaments\Tournament\Domain\Scoring\ScoringRulesFactory;
use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Domain\Stage\BracketSide;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\FixtureId;
use Tournaments\Tournament\Domain\Stage\FixtureSlot;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageFormat;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageRepository;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Stage\StageStatus;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\TournamentId;

/** Стадия и все её матчи. Матчи не удаляются по одному, поэтому сохранение — просто upsert. */
final readonly class PostgresStageRepository implements StageRepository
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function get(StageId $id): Stage
    {
        $row = $this->stages()->where('id', $id->value)->lockForUpdate()->first();
        if (! $row instanceof stdClass) {
            throw new StageNotFound(sprintf('Stage %s not found', $id));
        }

        return $this->hydrate($row);
    }

    public function ofTournament(TournamentId $tournamentId): array
    {
        return array_values($this->stages()
            ->where('tournament_id', $tournamentId->value)
            ->orderBy('phase')
            ->orderBy('name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (stdClass $row) => $this->hydrate($row))
            ->all());
    }

    public function save(Stage $stage): void
    {
        $now = (new DateTimeImmutable)->format(DATE_ATOM);

        $this->stages()->upsert([[
            'id' => $stage->id->value,
            'tournament_id' => $stage->tournamentId->value,
            'name' => $stage->name,
            'phase' => $stage->phase,
            'seeding' => json_encode(array_map(static fn (ParticipantId $id) => $id->value, $stage->seeding), JSON_THROW_ON_ERROR),
            'format' => $stage->format->value,
            'settings' => json_encode($stage->settings->toArray(), JSON_THROW_ON_ERROR),
            'status' => $stage->status->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['id'], ['settings', 'status', 'seeding', 'updated_at']);

        $rows = array_map(static fn (Fixture $f) => [
            'id' => $f->id->value,
            'stage_id' => $stage->id->value,
            'round' => $f->round,
            'position' => $f->position,
            'bracket' => $f->bracket?->value,
            'home_id' => $f->home()?->value,
            'away_id' => $f->away()?->value,
            'outcome' => $f->outcome() === null ? null : json_encode($f->outcome()->toArray(), JSON_THROW_ON_ERROR),
            'bye' => $f->isBye(),
            'finished_at' => $f->finishedAt()?->format(DATE_ATOM),
            'next_win_fixture_id' => $f->nextWin?->fixtureId->value,
            'next_win_side' => $f->nextWin?->side->value,
            'next_lose_fixture_id' => $f->nextLose?->fixtureId->value,
            'next_lose_side' => $f->nextLose?->side->value,
        ], $stage->fixtures());

        if ($rows !== []) {
            $this->fixtures()->upsert($rows, ['id'], ['home_id', 'away_id', 'outcome', 'bye', 'finished_at']);
        }
    }

    public function remove(Stage $stage): void
    {
        $this->stages()->where('id', $stage->id->value)->delete();
    }

    private function hydrate(stdClass $row): Stage
    {
        $fixtures = array_values($this->fixtures()
            ->where('stage_id', $row->id)
            ->orderBy('round')
            ->orderBy('position')
            ->get()
            ->map(static fn (stdClass $f) => new Fixture(
                id: FixtureId::fromString($f->id),
                round: (int) $f->round,
                position: (int) $f->position,
                home: $f->home_id === null ? null : ParticipantId::fromString($f->home_id),
                away: $f->away_id === null ? null : ParticipantId::fromString($f->away_id),
                outcome: $f->outcome === null ? null : ScoringRulesFactory::outcomeFromArray(Json::map($f->outcome)),
                bye: (bool) $f->bye,
                bracket: $f->bracket === null ? null : BracketSide::from($f->bracket),
                finishedAt: $f->finished_at === null ? null : new DateTimeImmutable($f->finished_at),
                nextWin: self::slot($f->next_win_fixture_id, $f->next_win_side),
                nextLose: self::slot($f->next_lose_fixture_id, $f->next_lose_side),
            ))
            ->all());

        return Stage::reconstitute(
            id: StageId::fromString($row->id),
            tournamentId: TournamentId::fromString($row->tournament_id),
            format: StageFormat::from($row->format),
            settings: StageSettings::fromArray(Json::map($row->settings)),
            status: StageStatus::from($row->status),
            fixtures: $fixtures,
            name: $row->name,
            phase: (int) $row->phase,
            seeding: array_map(ParticipantId::fromString(...), Json::list($row->seeding)),
        );
    }

    private static function slot(?string $fixtureId, ?string $side): ?FixtureSlot
    {
        if ($fixtureId === null || $side === null) {
            return null;
        }

        return new FixtureSlot(FixtureId::fromString($fixtureId), Side::from($side));
    }

    private function stages(): Builder
    {
        return $this->db->table('stages');
    }

    private function fixtures(): Builder
    {
        return $this->db->table('fixtures');
    }
}
