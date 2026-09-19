<?php

declare(strict_types=1);

namespace Tests\Domain\Stage;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Scoring\PointBasedRules;
use Tournaments\Tournament\Domain\Scoring\PointOutcome;
use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageFormat;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\TournamentId;

final class RoundRobinStageTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-09-18 12:00:00');
    }

    /** @return iterable<string, array{int, int, int, int}> */
    public static function sizes(): iterable
    {
        yield '2 participants' => [2, 1, 1, 1];
        yield '3 participants' => [3, 3, 3, 1];
        yield '4 participants' => [4, 6, 3, 2];
        yield '5 participants' => [5, 10, 5, 2];
        yield '8 participants' => [8, 28, 7, 4];
        yield '9 participants' => [9, 36, 9, 4];
    }

    #[Test]
    #[DataProvider('sizes')]
    public function everyone_meets_everyone_exactly_once(int $count, int $fixtures, int $rounds, int $perRound): void
    {
        [$stage, $ids] = $this->stage($count);

        self::assertCount($fixtures, $stage->fixtures());
        self::assertSame($rounds, $stage->roundsCount());

        $seen = [];
        for ($round = 1; $round <= $rounds; $round++) {
            $inRound = $stage->fixturesOfRound($round);
            self::assertCount($perRound, $inRound);
            $playing = [];
            foreach ($inRound as $fixture) {
                self::assertTrue($fixture->isReady());
                $playing[] = (string) $fixture->home();
                $playing[] = (string) $fixture->away();
                $key = implode('|', [min((string) $fixture->home(), (string) $fixture->away()), max((string) $fixture->home(), (string) $fixture->away())]);
                $seen[$key] = ($seen[$key] ?? 0) + 1;
            }
            self::assertSame($playing, array_unique($playing), "round $round: a participant plays twice");
        }

        self::assertCount(intdiv($count * ($count - 1), 2), $seen);
        self::assertSame([1], array_values(array_unique($seen)));
    }

    #[Test]
    public function home_and_away_are_balanced(): void
    {
        [$stage, $ids] = $this->stage(6);

        $home = [];
        foreach ($stage->fixtures() as $fixture) {
            $home[(string) $fixture->home()] = ($home[(string) $fixture->home()] ?? 0) + 1;
        }

        foreach ($ids as $id) {
            self::assertGreaterThanOrEqual(2, $home[(string) $id] ?? 0);
            self::assertLessThanOrEqual(3, $home[(string) $id] ?? 0);
        }
    }

    #[Test]
    public function two_legs_repeat_with_swapped_sides(): void
    {
        [$stage] = $this->stage(4, new StageSettings(legs: 2));

        self::assertCount(12, $stage->fixtures());
        self::assertSame(6, $stage->roundsCount());

        $first = array_map(static fn (Fixture $f) => [(string) $f->home(), (string) $f->away()], array_merge(...array_map($stage->fixturesOfRound(...), [1, 2, 3])));
        $second = array_map(static fn (Fixture $f) => [(string) $f->away(), (string) $f->home()], array_merge(...array_map($stage->fixturesOfRound(...), [4, 5, 6])));
        self::assertSame($first, $second);
    }

    #[Test]
    public function two_participants_play_a_single_match(): void
    {
        [$stage] = $this->stage(2);

        self::assertCount(1, $stage->fixtures());
        self::assertSame(1, $stage->roundsCount());
    }

    #[Test]
    public function needs_at_least_two_participants(): void
    {
        $this->expectException(NotEnoughParticipants::class);

        $this->stage(1);
    }

    #[Test]
    public function draws_are_allowed_when_rules_allow_and_stage_completes_when_all_played(): void
    {
        [$stage] = $this->stage(3);
        $rules = new PointBasedRules(drawAllowed: true);

        foreach ($stage->fixtures() as $fixture) {
            $stage->recordResult($fixture->id, new PointOutcome(1, 1), $rules, $this->now);
            self::assertNull($fixture->winner());
        }

        self::assertTrue($stage->isCompleted());
    }

    #[Test]
    public function results_do_not_move_anyone(): void
    {
        [$stage] = $this->stage(4);
        $before = array_map(static fn (Fixture $f) => [(string) $f->home(), (string) $f->away()], $stage->fixtures());

        $stage->recordResult($stage->fixtures()[0]->id, new PointOutcome(3, 0), new PointBasedRules, $this->now);

        self::assertSame($before, array_map(static fn (Fixture $f) => [(string) $f->home(), (string) $f->away()], $stage->fixtures()));
        self::assertSame(Side::Home, $stage->fixtures()[0]->outcome()?->winner());
    }

    /** @return array{Stage, list<ParticipantId>} */
    private function stage(int $count, ?StageSettings $settings = null): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = ParticipantId::generate();
        }

        return [Stage::generate(StageId::generate(), TournamentId::generate(), StageFormat::RoundRobin, $settings ?? StageSettings::default(), $ids, $this->now), $ids];
    }
}
