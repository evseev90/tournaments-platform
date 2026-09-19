<?php

declare(strict_types=1);

namespace Tests\Domain\Scheduling;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tournaments\Scheduling\Domain\Assignment;
use Tournaments\Scheduling\Domain\CompetitorId;
use Tournaments\Scheduling\Domain\Exception\NoVenues;
use Tournaments\Scheduling\Domain\MatchId;
use Tournaments\Scheduling\Domain\Planning\GreedyPlanner;
use Tournaments\Scheduling\Domain\Planning\PlannableMatch;
use Tournaments\Scheduling\Domain\ScheduleSettings;
use Tournaments\Scheduling\Domain\Venue;
use Tournaments\Scheduling\Domain\VenueId;

final class GreedyPlannerTest extends TestCase
{
    private DateTimeImmutable $start;

    protected function setUp(): void
    {
        $this->start = new DateTimeImmutable('2026-10-03 10:00:00');
    }

    #[Test]
    public function needs_at_least_one_venue(): void
    {
        $this->expectException(NoVenues::class);

        (new GreedyPlanner)->plan([], [], $this->settings());
    }

    #[Test]
    public function spreads_simultaneous_matches_across_venues(): void
    {
        $people = $this->competitors(8);
        $matches = [
            $this->match(1, 1, $people[0], $people[1]),
            $this->match(1, 2, $people[2], $people[3]),
            $this->match(1, 3, $people[4], $people[5]),
            $this->match(1, 4, $people[6], $people[7]),
        ];

        $plan = (new GreedyPlanner)->plan($matches, $this->venues(2), $this->settings());

        self::assertSame(['10:00', '10:00', '10:30', '10:30'], $this->times($plan));
        self::assertSame(['Стол 1', 'Стол 2', 'Стол 1', 'Стол 2'], $this->venueNames($plan, $this->venues(2)));
    }

    #[Test]
    public function one_venue_means_one_after_another(): void
    {
        $people = $this->competitors(4);
        $matches = [
            $this->match(1, 1, $people[0], $people[1]),
            $this->match(1, 2, $people[2], $people[3]),
        ];

        $plan = (new GreedyPlanner)->plan($matches, $this->venues(1), new ScheduleSettings($this->start, matchMinutes: 40, breakMinutes: 5));

        self::assertSame(['10:00', '10:45'], $this->times($plan));
    }

    #[Test]
    public function competitor_gets_rest_between_own_matches(): void
    {
        $people = $this->competitors(3);
        $matches = [
            $this->match(1, 1, $people[0], $people[1]),
            $this->match(1, 2, $people[0], $people[2]),
        ];

        $plan = (new GreedyPlanner)->plan($matches, $this->venues(3), new ScheduleSettings($this->start, matchMinutes: 30, restMinutes: 15));

        // Второй матч на свободной площадке, но игрок 1 отдыхает: 10:00 + 30 игры + 15 отдыха.
        self::assertSame(['10:00', '10:45'], $this->times($plan));
    }

    #[Test]
    public function next_round_waits_for_the_matches_that_feed_it(): void
    {
        $people = $this->competitors(4);
        $semiOne = $this->match(1, 1, $people[0], $people[1]);
        $semiTwo = $this->match(1, 2, $people[2], $people[3]);
        $final = new PlannableMatch(MatchId::generate(), 2, 1, null, null, [$semiOne->id, $semiTwo->id]);

        $plan = (new GreedyPlanner)->plan([$semiOne, $semiTwo, $final], $this->venues(4), new ScheduleSettings($this->start, matchMinutes: 30, restMinutes: 10));

        self::assertSame(['10:00', '10:00', '10:40'], $this->times($plan));
    }

    #[Test]
    public function known_competitors_of_a_later_round_still_get_rest(): void
    {
        $people = $this->competitors(3);
        $first = $this->match(1, 1, $people[0], $people[1]);
        // Победитель заранее известен и уже вписан в следующий матч: расписание обязано дать ему отдых.
        $second = new PlannableMatch(MatchId::generate(), 2, 1, $people[0], $people[2], [$first->id]);

        $plan = (new GreedyPlanner)->plan([$first, $second], $this->venues(2), new ScheduleSettings($this->start, matchMinutes: 25, restMinutes: 20));

        self::assertSame(['10:00', '10:45'], $this->times($plan));
    }

    #[Test]
    public function every_match_gets_exactly_one_slot(): void
    {
        $people = $this->competitors(6);
        $matches = [];
        $sequence = 1;
        for ($i = 0; $i < 6; $i++) {
            for ($j = $i + 1; $j < 6; $j++) {
                $matches[] = $this->match(1, $sequence++, $people[$i], $people[$j]);
            }
        }

        $plan = (new GreedyPlanner)->plan($matches, $this->venues(3), $this->settings());

        self::assertCount(15, $plan);
        self::assertCount(15, array_unique(array_map(static fn (Assignment $a) => $a->matchId->value, $plan)));
        $this->assertNoOverlaps($plan);
    }

    /** @param list<Assignment> $plan */
    private function assertNoOverlaps(array $plan): void
    {
        foreach ($plan as $i => $one) {
            foreach (array_slice($plan, $i + 1) as $other) {
                if (! $one->overlaps($other->startsAt, $other->endsAt())) {
                    continue;
                }
                self::assertFalse($one->venueId->equals($other->venueId), 'две игры на одной площадке одновременно');
                self::assertFalse($other->involves($one->home) || $other->involves($one->away), 'участник играет два матча сразу');
            }
        }
    }

    private function settings(): ScheduleSettings
    {
        return new ScheduleSettings($this->start, matchMinutes: 30, breakMinutes: 0, restMinutes: 0);
    }

    /** @return list<CompetitorId> */
    private function competitors(int $count): array
    {
        return array_map(static fn () => CompetitorId::generate(), range(1, $count));
    }

    /** @return list<Venue> */
    private function venues(int $count): array
    {
        static $ids = [];
        $result = [];
        for ($i = 1; $i <= $count; $i++) {
            $ids[$i] ??= VenueId::generate();
            $result[] = new Venue($ids[$i], "Стол $i", $i);
        }

        return $result;
    }

    private function match(int $order, int $sequence, CompetitorId $home, CompetitorId $away): PlannableMatch
    {
        return new PlannableMatch(MatchId::generate(), $order, $sequence, $home, $away);
    }

    /**
     * @param  list<Assignment>  $plan
     * @return list<string>
     */
    private function times(array $plan): array
    {
        return array_map(static fn (Assignment $a) => $a->startsAt->format('H:i'), $plan);
    }

    /**
     * @param  list<Assignment>  $plan
     * @param  list<Venue>  $venues
     * @return list<string>
     */
    private function venueNames(array $plan, array $venues): array
    {
        $names = [];
        foreach ($venues as $venue) {
            $names[$venue->id->value] = $venue->name;
        }

        return array_map(static fn (Assignment $a) => $names[$a->venueId->value], $plan);
    }
}
