<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage;

use DateTimeImmutable;
use Tournaments\Shared\Domain\RecordsEvents;
use Tournaments\Tournament\Domain\Event\FixtureResultRecorded;
use Tournaments\Tournament\Domain\Event\FixtureResultReverted;
use Tournaments\Tournament\Domain\Event\StageCompleted;
use Tournaments\Tournament\Domain\Event\StageGenerated;
use Tournaments\Tournament\Domain\Exception\CannotRevertResult;
use Tournaments\Tournament\Domain\Exception\FixtureNotFound;
use Tournaments\Tournament\Domain\Exception\FixtureNotReady;
use Tournaments\Tournament\Domain\Exception\InvalidOutcome;
use Tournaments\Tournament\Domain\Scoring\MatchOutcome;
use Tournaments\Tournament\Domain\Scoring\ScoringRules;
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
        $stage = new self($id, $tournamentId, $format, $settings, StageStatus::InProgress, $name, $phase, $fixtures);
        $stage->recordEvent(new StageGenerated($tournamentId, $id, $now));

        return $stage;
    }

    /** @param list<Fixture> $fixtures */
    public static function reconstitute(
        StageId $id,
        TournamentId $tournamentId,
        StageFormat $format,
        StageSettings $settings,
        StageStatus $status,
        array $fixtures,
        ?string $name = null,
        int $phase = 1,
    ): self {
        return new self($id, $tournamentId, $format, $settings, $status, $name, $phase, $fixtures);
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

        if ($this->allSettled()) {
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
     * Участники стадии в порядке появления в матчах первого раунда.
     * Для круговой это и есть состав группы.
     *
     * @return list<ParticipantId>
     */
    public function participants(): array
    {
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
