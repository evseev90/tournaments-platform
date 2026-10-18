<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use stdClass;
use Tournaments\Tournament\Domain\Exception\TournamentNotFound;
use Tournaments\Tournament\Domain\Scoring\ScoringRulesFactory;
use Tournaments\Tournament\Domain\Scoring\Sport;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Tournament\AdminToken;
use Tournaments\Tournament\Domain\Tournament\Name;
use Tournaments\Tournament\Domain\Tournament\OwnerId;
use Tournaments\Tournament\Domain\Tournament\Participant;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\ParticipantType;
use Tournaments\Tournament\Domain\Tournament\PublicSlug;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;
use Tournaments\Tournament\Domain\Tournament\TournamentStatus;

/**
 * Агрегат целиком: строка турнира плюс строки участников и стадий.
 * get() берёт строку с блокировкой: обработчики команд работают в транзакции, и два параллельных
 * изменения одного турнира выстраиваются в очередь.
 */
final readonly class PostgresTournamentRepository implements TournamentRepository
{
    private PostgresStageRepository $stages;

    public function __construct(
        private ConnectionInterface $db,
        ?PostgresStageRepository $stages = null,
    ) {
        $this->stages = $stages ?? new PostgresStageRepository($db);
    }

    public function get(TournamentId $id): Tournament
    {
        $row = $this->tournaments()->where('id', $id->value)->lockForUpdate()->first();
        if (! $row instanceof stdClass) {
            throw new TournamentNotFound(sprintf('Tournament %s not found', $id));
        }

        return $this->hydrate($row);
    }

    public function findByAdminToken(AdminToken $token): ?Tournament
    {
        $row = $this->tournaments()->where('admin_token', $token->value)->first();

        return $row instanceof stdClass ? $this->hydrate($row) : null;
    }

    public function findBySlug(PublicSlug $slug): ?Tournament
    {
        $row = $this->tournaments()->where('public_slug', $slug->value)->first();

        return $row instanceof stdClass ? $this->hydrate($row) : null;
    }

    public function findByStageId(StageId $stageId): ?Tournament
    {
        $row = $this->stagesTable()->where('id', $stageId->value)->first();
        if (! $row instanceof stdClass) {
            return null;
        }

        return $this->get(TournamentId::fromString($row->tournament_id));
    }

    public function save(Tournament $tournament): void
    {
        $now = (new DateTimeImmutable)->format(DATE_ATOM);

        $this->tournaments()->upsert([[
            'id' => $tournament->id->value,
            'name' => $tournament->name->value,
            'sport' => $tournament->sport->value,
            'rules' => json_encode($tournament->rules->toArray(), JSON_THROW_ON_ERROR),
            'status' => $tournament->status->value,
            'admin_token' => $tournament->adminToken->value,
            'public_slug' => $tournament->publicSlug->value,
            'created_at' => $tournament->createdAt->format(DATE_ATOM),
            'updated_at' => $now,
            'owner_id' => $tournament->ownerId?->value,
        ]], ['id'], ['name', 'sport', 'rules', 'status', 'owner_id', 'updated_at']);

        $participants = $tournament->participants();
        $ids = array_map(static fn (Participant $p) => $p->id->value, $participants);

        $this->participants()->where('tournament_id', $tournament->id->value)->whereNotIn('id', $ids)->delete();

        if ($participants !== []) {
            $this->participants()->upsert(array_map(static fn (Participant $p) => [
                'id' => $p->id->value,
                'tournament_id' => $tournament->id->value,
                'name' => $p->name->value,
                'type' => $p->type->value,
                'members' => json_encode($p->members, JSON_THROW_ON_ERROR),
                'seed' => $p->seed,
            ], $participants), ['id'], ['name', 'type', 'members', 'seed']);
        }

        $currentStages = $tournament->stages();
        $stageIds = array_map(static fn (Stage $s) => $s->id->value, $currentStages);

        $this->stagesTable()
            ->where('tournament_id', $tournament->id->value)
            ->whereNotIn('id', $stageIds)
            ->delete();

        foreach ($currentStages as $stage) {
            $this->stages->save($stage);
        }
    }

    private function hydrate(stdClass $row): Tournament
    {
        $participants = $this->participants()
            ->where('tournament_id', $row->id)
            ->orderBy('seed')
            ->get()
            ->map(static fn (stdClass $p) => new Participant(
                id: ParticipantId::fromString($p->id),
                name: new Name($p->name),
                type: ParticipantType::from($p->type),
                members: Json::list($p->members),
                seed: (int) $p->seed,
            ))
            ->all();

        $stages = $this->stages->ofTournament(TournamentId::fromString($row->id));

        return Tournament::reconstitute(
            id: TournamentId::fromString($row->id),
            name: new Name($row->name),
            sport: Sport::from($row->sport),
            rules: ScoringRulesFactory::rulesFromArray(Json::map($row->rules)),
            status: TournamentStatus::from($row->status),
            adminToken: AdminToken::fromString($row->admin_token),
            publicSlug: PublicSlug::fromString($row->public_slug),
            createdAt: new DateTimeImmutable($row->created_at),
            participants: array_values($participants),
            ownerId: $row->owner_id === null ? null : new OwnerId((int) $row->owner_id),
            stages: $stages,
        );
    }

    private function tournaments(): Builder
    {
        return $this->db->table('tournaments');
    }

    private function participants(): Builder
    {
        return $this->db->table('participants');
    }

    private function stagesTable(): Builder
    {
        return $this->db->table('stages');
    }
}
