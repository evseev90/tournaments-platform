<?php

declare(strict_types=1);

namespace Tests\Domain\Scoring;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tournaments\Tournament\Domain\Exception\InvalidOutcome;
use Tournaments\Tournament\Domain\Scoring\PointOutcome;
use Tournaments\Tournament\Domain\Scoring\ScoringRulesFactory;
use Tournaments\Tournament\Domain\Scoring\SetBasedOutcome;
use Tournaments\Tournament\Domain\Scoring\SetBasedRules;
use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Domain\Scoring\Sport;
use Tournaments\Tournament\Domain\Scoring\Walkover;

final class SetBasedRulesTest extends TestCase
{
    /** @return iterable<string, array{Sport, list<array{0: int, 1: int}>, Side}> */
    public static function validOutcomes(): iterable
    {
        yield 'volleyball 3:0' => [Sport::Volleyball, [[25, 20], [25, 23], [25, 10]], Side::Home];
        yield 'volleyball 3:2 with deciding set to 15' => [Sport::Volleyball, [[25, 20], [20, 25], [25, 27], [30, 28], [15, 13]], Side::Home];
        yield 'volleyball deuce continues by two' => [Sport::Volleyball, [[26, 24], [24, 26], [33, 31], [23, 25], [16, 18]], Side::Away];
        yield 'volleyball long deuce in deciding set' => [Sport::Volleyball, [[25, 20], [20, 25], [25, 20], [20, 25], [25, 23]], Side::Home];
        yield 'beach 2:0' => [Sport::BeachVolleyball, [[21, 19], [21, 15]], Side::Home];
        yield 'beach 2:1 deciding to 15' => [Sport::BeachVolleyball, [[21, 19], [19, 21], [15, 13]], Side::Home];
        yield 'table tennis 3:1' => [Sport::TableTennis, [[11, 9], [12, 10], [8, 11], [11, 5]], Side::Home];
        yield 'table tennis 3:2 deciding still to 11' => [Sport::TableTennis, [[11, 9], [9, 11], [11, 7], [10, 12], [11, 9]], Side::Home];
    }

    #[Test]
    #[DataProvider('validOutcomes')]
    public function accepts_valid_outcomes(Sport $sport, array $sets, Side $winner): void
    {
        $outcome = SetBasedOutcome::of($sets);

        $sport->defaultRules()->validate($outcome);

        self::assertSame($winner, $outcome->winner());
        self::assertFalse($outcome->isDraw());
    }

    /** @return iterable<string, array{Sport, list<array{0: int, 1: int}>, string}> */
    public static function invalidOutcomes(): iterable
    {
        yield 'set without margin' => [Sport::Volleyball, [[25, 24], [25, 20], [25, 20]], 'margin'];
        yield 'set past deuce with margin of three' => [Sport::Volleyball, [[27, 24], [25, 20], [25, 20]], 'deuce'];
        yield 'set below target' => [Sport::Volleyball, [[20, 18], [25, 20], [25, 20]], 'at least 25'];
        yield 'winner has too few sets' => [Sport::Volleyball, [[25, 20], [25, 20]], 'exactly 3 sets'];
        yield 'winner has too many sets' => [Sport::Volleyball, [[25, 20], [25, 20], [25, 20], [25, 20]], 'exactly 3 sets'];
        yield 'deciding set past deuce with margin of three' => [Sport::Volleyball, [[25, 20], [20, 25], [25, 20], [20, 25], [18, 15]], 'deuce'];
        yield 'beach deciding set below 15' => [Sport::BeachVolleyball, [[21, 19], [19, 21], [11, 9]], 'at least 15'];
        yield 'table tennis 13:10' => [Sport::TableTennis, [[13, 10], [11, 5], [11, 5]], 'deuce'];
    }

    #[Test]
    #[DataProvider('invalidOutcomes')]
    public function rejects_invalid_outcomes(Sport $sport, array $sets, string $messagePart): void
    {
        $this->expectException(InvalidOutcome::class);
        $this->expectExceptionMessage($messagePart);

        $sport->defaultRules()->validate(SetBasedOutcome::of($sets));
    }

    #[Test]
    public function set_cannot_be_tied(): void
    {
        $this->expectException(InvalidOutcome::class);

        SetBasedOutcome::of([[25, 25]]);
    }

    #[Test]
    public function rejects_outcome_of_another_kind(): void
    {
        $this->expectException(InvalidOutcome::class);

        SetBasedRules::volleyball()->validate(new PointOutcome(3, 1));
    }

    #[Test]
    public function accepts_walkover(): void
    {
        $walkover = new Walkover(Side::Away);

        SetBasedRules::tableTennis()->validate($walkover);

        self::assertSame(Side::Away, $walkover->winner());
    }

    #[Test]
    public function counts_sets_and_points(): void
    {
        $outcome = SetBasedOutcome::of([[25, 20], [20, 25], [25, 23], [15, 10]]);

        self::assertSame(3, $outcome->setsWon(Side::Home));
        self::assertSame(1, $outcome->setsWon(Side::Away));
        self::assertSame(85, $outcome->pointsWon(Side::Home));
        self::assertSame(78, $outcome->pointsWon(Side::Away));
    }

    #[Test]
    public function rules_and_outcomes_survive_serialization(): void
    {
        $rules = SetBasedRules::beachVolleyball();
        $outcome = SetBasedOutcome::of([[21, 19], [21, 15]]);

        $restoredRules = ScoringRulesFactory::rulesFromArray($rules->toArray());
        $restoredOutcome = ScoringRulesFactory::outcomeFromArray($outcome->toArray());

        self::assertEquals($rules, $restoredRules);
        self::assertTrue($outcome->equals($restoredOutcome));
        self::assertTrue((new Walkover(Side::Home))->equals(ScoringRulesFactory::outcomeFromArray(['type' => 'walkover', 'winner' => 'home'])));
    }
}
