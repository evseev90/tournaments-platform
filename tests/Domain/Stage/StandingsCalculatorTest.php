<?php

declare(strict_types=1);

namespace Tests\Domain\Stage;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tournaments\Tournament\Domain\Scoring\MatchOutcome;
use Tournaments\Tournament\Domain\Scoring\PointBasedRules;
use Tournaments\Tournament\Domain\Scoring\PointOutcome;
use Tournaments\Tournament\Domain\Scoring\ScoringRules;
use Tournaments\Tournament\Domain\Scoring\SetBasedOutcome;
use Tournaments\Tournament\Domain\Scoring\SetBasedRules;
use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Domain\Scoring\Walkover;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageFormat;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Stage\StandingRow;
use Tournaments\Tournament\Domain\Stage\StandingsCalculator;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\TournamentId;

final class StandingsCalculatorTest extends TestCase
{
    /** @var list<ParticipantId> */
    private array $ids;

    private Stage $stage;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-09-18');
        $this->ids = [ParticipantId::generate(), ParticipantId::generate(), ParticipantId::generate(), ParticipantId::generate()];
        $this->stage = Stage::generate(StageId::generate(), TournamentId::generate(), StageFormat::RoundRobin, StageSettings::default(), $this->ids, $this->now);
    }

    #[Test]
    public function empty_table_follows_seeding(): void
    {
        $rows = (new StandingsCalculator)->calculate($this->ids, $this->stage->fixtures());

        self::assertSame([1, 2, 3, 4], array_map(static fn (StandingRow $r) => $r->rank, $rows));
        self::assertSame([0, 0, 0, 0], array_map(static fn (StandingRow $r) => $r->played, $rows));
        self::assertTrue($rows[0]->participantId->equals($this->ids[0]));
    }

    #[Test]
    public function wins_then_head_to_head_then_set_ratio(): void
    {
        [$a, $b, $c, $d] = $this->ids;
        $rules = SetBasedRules::tableTennis();
        // A и B по две победы: B выше по личной встрече. C и D по одной: D выше, потому что обыграл C.
        $this->play($a, $c, [[11, 1], [11, 1], [11, 1]], $rules);
        $this->play($a, $d, [[11, 1], [11, 1], [11, 1]], $rules);
        $this->play($b, $a, [[11, 9], [11, 9], [9, 11], [11, 9]], $rules);
        $this->play($b, $d, [[11, 9], [9, 11], [11, 9], [11, 9]], $rules);
        $this->play($c, $b, [[11, 9], [9, 11], [11, 9], [11, 9]], $rules);
        $this->playOutcome($c, $d, new Walkover(Side::Away), $rules);

        $rows = (new StandingsCalculator)->calculate($this->ids, $this->stage->fixtures());
        $order = array_map(fn (StandingRow $r) => array_search($r->participantId->value, array_map(static fn ($i) => $i->value, $this->ids), true), $rows);

        self::assertSame([1, 0, 3, 2], $order);
        self::assertSame([1, 2, 3, 4], array_map(static fn (StandingRow $r) => $r->rank, $rows));
        self::assertSame([3, 3, 3, 3], array_map(static fn (StandingRow $r) => $r->played, $rows));
        self::assertSame([2, 2, 1, 1], array_map(static fn (StandingRow $r) => $r->wins, $rows));
        self::assertSame(7, $rows[1]->setsWon, 'A: 3 + 3 + 1');
        self::assertSame(1, $rows[2]->setsWon, 'D: одна партия у B, техническая победа партий не даёт');
    }

    #[Test]
    public function draws_give_one_point(): void
    {
        [$a, $b, $c, $d] = $this->ids;
        $rules = new PointBasedRules;
        $this->playOutcome($a, $b, new PointOutcome(1, 1), $rules);
        $this->playOutcome($c, $d, new PointOutcome(2, 0), $rules);

        $rows = (new StandingsCalculator)->calculate($this->ids, $this->stage->fixtures());

        self::assertTrue($rows[0]->participantId->equals($c));
        self::assertSame(2, $rows[0]->score());
        self::assertSame(1, $rows[1]->score());
        self::assertSame(1, $rows[1]->draws);
        self::assertSame(0, $rows[3]->score());
    }

    /** @param list<array{0: int, 1: int}> $sets */
    private function play(ParticipantId $home, ParticipantId $away, array $sets, SetBasedRules $rules): void
    {
        $this->playOutcome($home, $away, SetBasedOutcome::of($sets), $rules);
    }

    private function playOutcome(ParticipantId $x, ParticipantId $y, MatchOutcome $outcome, ScoringRules $rules): void
    {
        $fixture = array_find($this->stage->fixtures(), static fn (Fixture $f) => $f->sideOf($x) !== null && $f->sideOf($y) !== null);
        self::assertNotNull($fixture);
        // Генератор мог поставить x на away: тогда счёт зеркалим.
        if ($fixture->sideOf($x) === Side::Away) {
            $outcome = match (true) {
                $outcome instanceof SetBasedOutcome => SetBasedOutcome::of(array_map(static fn ($s) => [$s->away, $s->home], $outcome->sets)),
                $outcome instanceof PointOutcome => new PointOutcome($outcome->away, $outcome->home),
                $outcome instanceof Walkover => new Walkover($outcome->winnerSide->opposite()),
                default => $outcome,
            };
        }
        $this->stage->recordResult($fixture->id, $outcome, $rules, $this->now);
    }
}
