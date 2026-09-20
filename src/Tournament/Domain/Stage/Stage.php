<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage;

use DateTimeImmutable;
use Tournaments\Shared\Domain\RecordsEvents;
use Tournaments\Tournament\Domain\Event\FixtureResultRecorded;
use Tournaments\Tournament\Domain\Event\FixtureResultReverted;
use Tournaments\Tournament\Domain\Event\RoundPaired;
use Tournaments\Tournament\Domain\Event\StageCompleted;
use Tournaments\Tournament\Domain\Event\StageGenerated;
use Tournaments\Tournament\Domain\Exception\CannotRevertResult;
use Tournaments\Tournament\Domain\Exception\FixtureNotFound;
use Tournaments\Tournament\Domain\Exception\FixtureNotReady;
use Tournaments\Tournament\Domain\Exception\InvalidOutcome;
use Tournaments\Tournament\Domain\Exception\RoundNotFinished;
use Tournaments\Tournament\Domain\Scoring\MatchOutcome;
use Tournaments\Tournament\Domain\Scoring\ScoringRules;
use Tournaments\Tournament\Domain\Stage\Format\SwissFirstRound;
use Tournaments\Tournament\Domain\Stage\Format\SwissPairing;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\TournamentId;

/**
 * Агрегат «Стадия»: формат и граф матчей. Результат матча продвигает участников
 * по ссылкам nextWin / nextLose внутри этого же агрегата, поэтому сетка всегда согласована.
 *
 * Этап турнира (`phase`) может состоять из нескольких стадий: групповой этап — это
 * несколько круговых стадий с одним номером этапа и разными именами. Плей-офф идёт следующим этапом.
 */
final class Stage
{
    use RecordsEvents;

    /** @var array<string, Fixture> */
    private array $fixtures = [];

    /** @param list<Fixture> $fixtures */
    private function __construct(
        public readonly StageId $id,
        public readonly TournamentId $tournamentId,
        public readonly StageFormat $format,
        public readonly StageSettings $settings,
        public private(set) StageStatus $status,
        public readonly ?string $name,
        public readonly int $phase,
        /** @var list<ParticipantId> исходный порядок посева */
        public readonly array $seeding,
        array $fixtures,
    ) {
        foreach ($fixtures as $fixture) {
            $this->fixtures[$fixture->id->value] = $fixture;
        }
    }

    /** @param list<ParticipantId> $seeded участники в порядке посева */
    public static function generate(
        StageId $id,
        TournamentId $tournamentId,
        StageFormat $format,
        StageSettings $settings,
        array $seeded,
        DateTimeImmutable $now,
        ?string $name = null,
        int $phase = 1,
    ): self {
        $fixtures = $format->generator()->generate($seeded, $settings);
        $stage = new self($id, $tournamentId, $format, $settings, StageStatus::InProgress, $name, $phase, $seeded, $fixtures);
        $stage->recordEvent(new StageGenerated($tournamentId, $id, $now));

        return $stage;
    }

    /**
     * @param  list<Fixture>  $fixtures
     * @param  list<ParticipantId>  $seeding
     */
    public static function reconstitute(
        StageId $id,
        TournamentId $tournamentId,
        StageFormat $format,
        StageSettings $settings,
        StageStatus $status,
        array $fixtures,
        ?string $name = null,
        int $phase = 1,
        array $seeding = [],
    ): self {
        return new self($id, $tournamentId, $format, $settings, $status, $name, $phase, $seeding, $fixtures);
    }

    /**
     * Жеребьёвка очередного тура швейцарской системы. Доступна, когда текущий тур доигран
     * и плановые туры ещё не кончились.
     */
    public function pairNextRound(SwissPairing $pairing, DateTimeImmutable $now): void
    {
        if (! $this->needsMoreRounds()) {
            throw new RoundNotFinished('Все туры сыграны');
        }
        if (! $this->allSettled()) {
            throw new RoundNotFinished('Сначала внесите результаты текущего тура');
        }

        $round = $this->roundsCount() + 1;
        $pairs = $pairing->pair($this->seeding, $this->fixtures(), $round);

        foreach (SwissFirstRound::materialize($pairs, $round) as $fixture) {
            $this->fixtures[$fixture->id->value] = $fixture;
        }

        $this->status = StageStatus::InProgress;
        $this->recordEvent(new RoundPaired($this->tournamentId, $this->id, $round, $now));
    }

    /** Сколько туров играет швейцарская система: из настроек или по числу участников. */
    public function plannedRounds(): int
    {
        if (! $this->format->isPairedByRound()) {
            return $this->roundsCount();
        }

        return $this->settings->rounds > 0
            ? $this->settings->rounds
            : SwissPairing::suggestedRounds(count($this->seeding));
    }

    public function needsMoreRounds(): bool
    {
        return $this->format->isPairedByRound() && $this->roundsCount() < $this->plannedRounds();
    }

    /** Текущий тур доигран, но турнир ещё не закончен: пора жеребьёвки. */
    public function awaitsNextRound(): bool
    {
        return $this->needsMoreRounds() && $this->allSettled();
    }

    /**
     * Записывает результат. Если матч уже сыгран, а следующие ещё нет, результат перезаписывается.
     */
    public function recordResult(FixtureId $fixtureId, MatchOutcome $outcome, ScoringRules $rules, DateTimeImmutable $now): void
    {
        $fixture = $this->fixture($fixtureId);

        if ($fixture->isFinished()) {
            $this->revertResult($fixtureId, $now);
        }
        if (! $fixture->isReady()) {
            throw new FixtureNotReady(sprintf('Fixture %s is %s and cannot take a result', $fixtureId, $fixture->status()->value));
        }

        $rules->validate($outcome);
        if ($outcome->isDraw() && $this->format->isElimination()) {
            throw new InvalidOutcome('Elimination matches cannot end in a draw');
        }

        $fixture->finish($outcome);
        $this->advance($fixture);
        $this->recordEvent(new FixtureResultRecorded($this->tournamentId, $this->id, $fixtureId, $outcome, $now));

        if ($this->allSettled() && ! $this->needsMoreRounds()) {
            $this->status = StageStatus::Completed;
            $this->recordEvent(new StageCompleted($this->tournamentId, $this->id, $now));
        }
    }

    /** Отменяет результат, если участники ещё не сыграли следующие матчи. */
    public function revertResult(FixtureId $fixtureId, DateTimeImmutable $now): void
    {
        $fixture = $this->fixture($fixtureId);
        if (! $fixture->isFinished()) {
            throw new CannotRevertResult(sprintf('Fixture %s has no result to revert', $fixtureId));
        }

        foreach ([$fixture->nextWin, $fixture->nextLose] as $slot) {
            if ($slot === null) {
                continue;
            }
            $next = $this->fixture($slot->fixtureId);
            if ($next->isFinished()) {
                throw new CannotRevertResult('The following match has already been played');
            }
            $next->assign($slot->side, null);
        }

        $fixture->reopen();
        $this->status = StageStatus::InProgress;
        $this->recordEvent(new FixtureResultReverted($this->tournamentId, $this->id, $fixtureId, $now));
    }

    /**
     * Участники стадии. Для стадий, созданных до появления явного посева, состав
     * восстанавливается по матчам.
     *
     * @return list<ParticipantId>
     */
    public function participants(): array
    {
        if ($this->seeding !== []) {
            return $this->seeding;
        }

        $seen = [];
        foreach ($this->fixtures() as $fixture) {
            foreach ([$fixture->home(), $fixture->away()] as $participant) {
                if ($participant !== null) {
                    $seen[$participant->value] = $participant;
                }
            }
        }

        return array_values($seen);
    }

    public function fixture(FixtureId $id): Fixture
    {
        return $this->fixtures[$id->value]
            ?? throw new FixtureNotFound(sprintf('Fixture %s not found in stage %s', $id, $this->id));
    }

    /**
     * Матчи по раундам и позициям.
     *
     * @return list<Fixture>
     */
    public function fixtures(): array
    {
        $list = array_values($this->fixtures);
        usort($list, static fn (Fixture $a, Fixture $b) => [$a->round, $a->position] <=> [$b->round, $b->position]);

        return $list;
    }

    /** @return list<Fixture> */
    public function fixturesOfRound(int $round): array
    {
        return array_values(array_filter($this->fixtures(), static fn (Fixture $f) => $f->round === $round));
    }

    public function roundsCount(): int
    {
        return max([0, ...array_map(static fn (Fixture $f) => $f->round, $this->fixtures())]);
    }

    public function isCompleted(): bool
    {
        return $this->status === StageStatus::Completed;
    }

    private function advance(Fixture $fixture): void
    {
        $winner = $fixture->winner();
        $loser = $fixture->loser();

        if ($fixture->nextWin !== null && $winner !== null) {
            $this->fixture($fixture->nextWin->fixtureId)->assign($fixture->nextWin->side, $winner);
        }
        if ($fixture->nextLose !== null && $loser !== null) {
            $this->fixture($fixture->nextLose->fixtureId)->assign($fixture->nextLose->side, $loser);
        }
    }

    private function allSettled(): bool
    {
        foreach ($this->fixtures as $fixture) {
            if (! $fixture->isSettled()) {
                return false;
            }
        }

        return true;
    }
}
