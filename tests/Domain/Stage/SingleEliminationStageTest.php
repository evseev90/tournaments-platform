<?php

declare(strict_types=1);

namespace Tests\Domain\Stage;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tournaments\Tournament\Domain\Event\FixtureResultRecorded;
use Tournaments\Tournament\Domain\Event\FixtureResultReverted;
use Tournaments\Tournament\Domain\Event\StageCompleted;
use Tournaments\Tournament\Domain\Event\StageGenerated;
use Tournaments\Tournament\Domain\Exception\CannotRevertResult;
use Tournaments\Tournament\Domain\Exception\FixtureNotReady;
use Tournaments\Tournament\Domain\Exception\InvalidOutcome;
use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Scoring\MatchOutcome;
use Tournaments\Tournament\Domain\Scoring\ScoringRules;
use Tournaments\Tournament\Domain\Scoring\SetBasedOutcome;
use Tournaments\Tournament\Domain\Scoring\SetBasedRules;
use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Domain\Scoring\Walkover;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\FixtureStatus;
use Tournaments\Tournament\Domain\Stage\Format\SingleElimination;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageFormat;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\TournamentId;

final class SingleEliminationStageTest extends TestCase
{
    private DateTimeImmutable $now;

    private ScoringRules $rules;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-09-18 12:00:00');
        $this->rules = SetBasedRules::tableTennis();
    }

    #[Test]
    public function seed_order_follows_standard_bracket_placement(): void
    {
        self::assertSame([1, 2], SingleElimination::seedOrder(2));
        self::assertSame([1, 4, 2, 3], SingleElimination::seedOrder(4));
        self::assertSame([1, 8, 4, 5, 2, 7, 3, 6], SingleElimination::seedOrder(8));
        self::assertSame(16, SingleElimination::bracketSize(9));
        self::assertSame(8, SingleElimination::bracketSize(8));
    }

    #[Test]
    public function eight_participants_produce_full_bracket(): void
    {
        [$stage, $ids] = $this->stage(8);

        self::assertCount(7, $stage->fixtures());
        self::assertSame(3, $stage->roundsCount());
        self::assertSame([[1, 8], [4, 5], [2, 7], [3, 6]], $this->pairsOfRound($stage, 1, $ids));
        self::assertSame(array_fill(0, 4, FixtureStatus::Ready), array_map(static fn (Fixture $f) => $f->status(), $stage->fixturesOfRound(1)));
        self::assertSame(array_fill(0, 2, FixtureStatus::Pending), array_map(static fn (Fixture $f) => $f->status(), $stage->fixturesOfRound(2)));
        self::assertInstanceOf(StageGenerated::class, $stage->releaseEvents()[0]);
    }

    #[Test]
    public function top_seeds_get_byes_and_advance_immediately(): void
    {
        [$stage, $ids] = $this->stage(5);

        $round1 = $stage->fixturesOfRound(1);
        self::assertCount(4, $round1);
        self::assertSame([FixtureStatus::Bye, FixtureStatus::Ready, FixtureStatus::Bye, FixtureStatus::Bye], array_map(static fn (Fixture $f) => $f->status(), $round1));

        $round2 = $stage->fixturesOfRound(2);
        self::assertTrue($round2[0]->home()?->equals($ids[1]) ?? false, 'seed 1 waits for the winner of 4 vs 5');
        self::assertNull($round2[0]->away());
        self::assertSame([[2, 3]], [$this->pair($round2[1], $ids)]);
        self::assertTrue($round2[1]->isReady());
    }

    #[Test]
    public function two_participants_is_just_a_final(): void
    {
        [$stage] = $this->stage(2);

        self::assertCount(1, $stage->fixtures());
        self::assertTrue($stage->fixtures()[0]->isReady());
    }

    #[Test]
    public function needs_at_least_two_participants(): void
    {
        $this->expectException(NotEnoughParticipants::class);

        $this->stage(1);
    }

    #[Test]
    public function winner_advances_to_next_round_slot(): void
    {
        [$stage, $ids] = $this->stage(4);
        $semi = $stage->fixturesOfRound(1);

        $stage->recordResult($semi[0]->id, $this->win(Side::Away), $this->rules, $this->now);

        $final = $stage->fixturesOfRound(2)[0];
        self::assertTrue($final->home()?->equals($ids[4]) ?? false);
        self::assertNull($final->away());
        self::assertSame(FixtureStatus::Finished, $semi[0]->status());
        self::assertInstanceOf(FixtureResultRecorded::class, array_last($stage->releaseEvents()));
    }

    #[Test]
    public function plays_whole_bracket_to_completion_with_third_place(): void
    {
        [$stage, $ids] = $this->stage(4, new StageSettings(thirdPlaceMatch: true));
        self::assertCount(4, $stage->fixtures());

        foreach ($stage->fixturesOfRound(1) as $semi) {
            $stage->recordResult($semi->id, $this->win(Side::Home), $this->rules, $this->now);
        }
        [$final, $third] = $stage->fixturesOfRound(2);
        self::assertSame([1, 2], $this->pair($final, $ids));
        self::assertSame([4, 3], $this->pair($third, $ids));

        $stage->recordResult($final->id, $this->win(Side::Home), $this->rules, $this->now);
        self::assertFalse($stage->isCompleted());

        $stage->recordResult($third->id, new Walkover(Side::Away), $this->rules, $this->now);
        self::assertTrue($stage->isCompleted());
        self::assertTrue($final->winner()?->equals($ids[1]) ?? false);
        self::assertTrue($third->winner()?->equals($ids[3]) ?? false);
        self::assertInstanceOf(StageCompleted::class, array_last($stage->releaseEvents()));
    }

    #[Test]
    public function byes_count_as_settled_for_completion(): void
    {
        [$stage] = $this->stage(3);

        foreach ([1, 2] as $round) {
            foreach ($stage->fixturesOfRound($round) as $fixture) {
                if ($fixture->isReady()) {
                    $stage->recordResult($fixture->id, $this->win(Side::Home), $this->rules, $this->now);
                }
            }
        }

        self::assertTrue($stage->isCompleted());
    }

    #[Test]
    public function cannot_score_a_match_without_both_participants(): void
    {
        [$stage] = $this->stage(4);

        $this->expectException(FixtureNotReady::class);
        $stage->recordResult($stage->fixturesOfRound(2)[0]->id, $this->win(Side::Home), $this->rules, $this->now);
    }

    #[Test]
    public function cannot_score_a_bye(): void
    {
        [$stage] = $this->stage(3);

        $this->expectException(FixtureNotReady::class);
        $stage->recordResult($stage->fixturesOfRound(1)[0]->id, $this->win(Side::Home), $this->rules, $this->now);
    }

    #[Test]
    public function validates_outcome_against_rules(): void
    {
        [$stage] = $this->stage(2);

        $this->expectException(InvalidOutcome::class);
        $stage->recordResult($stage->fixtures()[0]->id, SetBasedOutcome::of([[11, 9]]), $this->rules, $this->now);
    }

    #[Test]
    public function result_can_be_corrected_until_next_match_is_played(): void
    {
        [$stage, $ids] = $this->stage(4);
        $semi = $stage->fixturesOfRound(1)[0];
        $final = $stage->fixturesOfRound(2)[0];

        $stage->recordResult($semi->id, $this->win(Side::Home), $this->rules, $this->now);
        $stage->recordResult($semi->id, $this->win(Side::Away), $this->rules, $this->now);

        self::assertTrue($final->home()?->equals($ids[4]) ?? false);
        self::assertContainsOnlyInstancesOf(FixtureResultReverted::class, array_filter($stage->releaseEvents(), static fn ($e) => $e instanceof FixtureResultReverted));
    }

    #[Test]
    public function revert_clears_the_slot_and_reopens_stage(): void
    {
        [$stage] = $this->stage(2);
        $final = $stage->fixtures()[0];
        $stage->recordResult($final->id, $this->win(Side::Home), $this->rules, $this->now);
        self::assertTrue($stage->isCompleted());

        $stage->revertResult($final->id, $this->now);

        self::assertFalse($stage->isCompleted());
        self::assertTrue($final->isReady());
    }

    #[Test]
    public function cannot_revert_when_next_match_already_played(): void
    {
        [$stage] = $this->stage(4);
        foreach ($stage->fixturesOfRound(1) as $semi) {
            $stage->recordResult($semi->id, $this->win(Side::Home), $this->rules, $this->now);
        }
        $stage->recordResult($stage->fixturesOfRound(2)[0]->id, $this->win(Side::Home), $this->rules, $this->now);

        $this->expectException(CannotRevertResult::class);
        $stage->revertResult($stage->fixturesOfRound(1)[0]->id, $this->now);
    }

    #[Test]
    public function cannot_revert_unplayed_match(): void
    {
        [$stage] = $this->stage(2);

        $this->expectException(CannotRevertResult::class);
        $stage->revertResult($stage->fixtures()[0]->id, $this->now);
    }

    /**
     * @return array{Stage, array<int, ParticipantId>} стадия и участники по номеру посева
     */
    private function stage(int $count, ?StageSettings $settings = null): array
    {
        $ids = [];
        for ($seed = 1; $seed <= $count; $seed++) {
            $ids[$seed] = ParticipantId::generate();
        }
        $stage = Stage::generate(StageId::generate(), TournamentId::generate(), StageFormat::SingleElimination, $settings ?? StageSettings::default(), array_values($ids), $this->now);

        return [$stage, $ids];
    }

    private function win(Side $side): MatchOutcome
    {
        $set = $side === Side::Home ? [11, 5] : [5, 11];

        return SetBasedOutcome::of([$set, $set, $set]);
    }

    /**
     * @param  array<int, ParticipantId>  $ids
     * @return list<array{0: int|null, 1: int|null}>
     */
    private function pairsOfRound(Stage $stage, int $round, array $ids): array
    {
        return array_map(fn (Fixture $f) => $this->pair($f, $ids), $stage->fixturesOfRound($round));
    }

    /**
     * @param  array<int, ParticipantId>  $ids
     * @return array{0: int|null, 1: int|null}
     */
    private function pair(Fixture $fixture, array $ids): array
    {
        $seedOf = static function (?ParticipantId $id) use ($ids): ?int {
            foreach ($ids as $seed => $candidate) {
                if ($candidate->equals($id)) {
                    return $seed;
                }
            }

            return null;
        };

        return [$seedOf($fixture->home()), $seedOf($fixture->away())];
    }
}
