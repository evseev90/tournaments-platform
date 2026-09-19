<?php

declare(strict_types=1);

namespace Tests\Domain\Scheduling;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tournaments\Scheduling\Domain\Assignment;
use Tournaments\Scheduling\Domain\CompetitorId;
use Tournaments\Scheduling\Domain\Event\MatchMoved;
use Tournaments\Scheduling\Domain\Event\ScheduleCleared;
use Tournaments\Scheduling\Domain\Event\SchedulePlanned;
use Tournaments\Scheduling\Domain\Exception\CompetitorBusy;
use Tournaments\Scheduling\Domain\Exception\MatchNotScheduled;
use Tournaments\Scheduling\Domain\Exception\VenueBusy;
use Tournaments\Scheduling\Domain\Exception\VenueNotFound;
use Tournaments\Scheduling\Domain\MatchId;
use Tournaments\Scheduling\Domain\Planning\GreedyPlanner;
use Tournaments\Scheduling\Domain\Planning\PlannableMatch;
use Tournaments\Scheduling\Domain\Schedule;
use Tournaments\Scheduling\Domain\ScheduleId;
use Tournaments\Scheduling\Domain\ScheduleSettings;
use Tournaments\Scheduling\Domain\TournamentRef;
use Tournaments\Scheduling\Domain\Venue;
use Tournaments\Scheduling\Domain\VenueId;

final class ScheduleTest extends TestCase
{
    private DateTimeImmutable $start;

    private Schedule $schedule;

    protected function setUp(): void
    {
        $this->start = new DateTimeImmutable('2026-10-03 10:00:00');
        $this->schedule = Schedule::create(
            ScheduleId::generate(),
            TournamentRef::generate(),
            new ScheduleSettings($this->start, matchMinutes: 30, restMinutes: 0),
        );
    }

    #[Test]
    public function venues_are_numbered_in_order_of_addition(): void
    {
        $this->schedule->addVenue(VenueId::generate(), '  Стол   1 ');
        $this->schedule->addVenue(VenueId::generate(), 'Стол 2');

        self::assertSame(['Стол 1', 'Стол 2'], array_map(static fn (Venue $v) => $v->name, $this->schedule->venuesInOrder()));
        self::assertSame([1, 2], array_map(static fn (Venue $v) => $v->order, $this->schedule->venuesInOrder()));
    }

    #[Test]
    public function planning_fills_every_match_and_raises_event(): void
    {
        [$matches] = $this->plannedSchedule(venues: 2, matches: 4);

        self::assertCount(4, $this->schedule->assignmentsInOrder());
        self::assertTrue($this->schedule->isPlanned());
        foreach ($matches as $match) {
            self::assertNotNull($this->schedule->assignment($match->id));
        }
        $event = $this->schedule->releaseEvents()[0];
        self::assertInstanceOf(SchedulePlanned::class, $event);
        self::assertSame(4, $event->matches);
    }

    #[Test]
    public function planning_twice_replaces_previous_assignments(): void
    {
        [$matches] = $this->plannedSchedule(venues: 1, matches: 3);
        $firstTimes = $this->times();

        $this->schedule->changeSettings(new ScheduleSettings($this->start->modify('+2 hours'), matchMinutes: 20));
        $this->schedule->plan($matches, new GreedyPlanner, $this->start);

        self::assertCount(3, $this->schedule->assignmentsInOrder());
        self::assertSame(['10:00', '10:30', '11:00'], $firstTimes);
        self::assertSame(['12:00', '12:20', '12:40'], $this->times());
    }

    #[Test]
    public function removing_venue_drops_its_matches_and_renumbers_the_rest(): void
    {
        [, $venues] = $this->plannedSchedule(venues: 2, matches: 4);

        $this->schedule->removeVenue($venues[0]->id);

        self::assertCount(2, $this->schedule->assignmentsInOrder());
        self::assertSame([1], array_map(static fn (Venue $v) => $v->order, $this->schedule->venuesInOrder()));
        foreach ($this->schedule->assignmentsInOrder() as $assignment) {
            self::assertTrue($assignment->venueId->equals($venues[1]->id));
        }
        $this->expectException(VenueNotFound::class);
        $this->schedule->venue($venues[0]->id);
    }

    #[Test]
    public function match_moves_to_a_free_slot(): void
    {
        [$matches, $venues] = $this->plannedSchedule(venues: 2, matches: 4);
        $moved = $matches[0]->id;

        $this->schedule->move($moved, $venues[1]->id, $this->start->modify('+3 hours'), $this->start);

        $assignment = $this->schedule->assignment($moved);
        self::assertSame('13:00', $assignment->startsAt->format('H:i'));
        self::assertTrue($assignment->venueId->equals($venues[1]->id));
        self::assertInstanceOf(MatchMoved::class, array_last($this->schedule->releaseEvents()));
    }

    #[Test]
    public function cannot_double_book_a_venue(): void
    {
        [$matches, $venues] = $this->plannedSchedule(venues: 2, matches: 4);
        $target = $this->schedule->assignment($matches[1]->id);

        $this->expectException(VenueBusy::class);
        $this->schedule->move($matches[0]->id, $target->venueId, $target->startsAt, $this->start);
    }

    #[Test]
    public function cannot_put_the_same_competitor_in_two_places_at_once(): void
    {
        $shared = CompetitorId::generate();
        $matches = [
            new PlannableMatch(MatchId::generate(), 1, 1, $shared, CompetitorId::generate()),
            new PlannableMatch(MatchId::generate(), 1, 2, $shared, CompetitorId::generate()),
        ];
        $venues = $this->addVenues(2);
        $this->schedule->plan($matches, new GreedyPlanner, $this->start);

        $first = $this->schedule->assignment($matches[0]->id);

        $this->expectException(CompetitorBusy::class);
        $this->schedule->move($matches[1]->id, $venues[1]->id, $first->startsAt, $this->start);
    }

    #[Test]
    public function moving_onto_an_unknown_venue_or_match_fails(): void
    {
        [$matches] = $this->plannedSchedule(venues: 1, matches: 2);

        try {
            $this->schedule->move($matches[0]->id, VenueId::generate(), $this->start, $this->start);
            self::fail('expected VenueNotFound');
        } catch (VenueNotFound) {
        }

        $this->expectException(MatchNotScheduled::class);
        $this->schedule->assignment(MatchId::generate());
    }

    #[Test]
    public function clearing_leaves_venues_but_no_assignments(): void
    {
        $this->plannedSchedule(venues: 2, matches: 4);

        $this->schedule->clear($this->start);

        self::assertFalse($this->schedule->isPlanned());
        self::assertCount(2, $this->schedule->venuesInOrder());
        self::assertInstanceOf(ScheduleCleared::class, array_last($this->schedule->releaseEvents()));
    }

    #[Test]
    public function refresh_pulls_in_competitors_that_became_known(): void
    {
        $later = new PlannableMatch(MatchId::generate(), 2, 1, null, null);
        $this->addVenues(1);
        $this->schedule->plan([$later], new GreedyPlanner, $this->start);
        self::assertNull($this->schedule->assignment($later->id)->home);

        $home = CompetitorId::generate();
        $this->schedule->refreshCompetitors([new PlannableMatch($later->id, 2, 1, $home, CompetitorId::generate())]);

        self::assertTrue($this->schedule->assignment($later->id)->home?->equals($home) ?? false);
    }

    /** @return array{list<PlannableMatch>, list<Venue>} */
    private function plannedSchedule(int $venues, int $matches): array
    {
        $venueList = $this->addVenues($venues);
        $matchList = [];
        for ($i = 1; $i <= $matches; $i++) {
            $matchList[] = new PlannableMatch(MatchId::generate(), 1, $i, CompetitorId::generate(), CompetitorId::generate());
        }
        $this->schedule->plan($matchList, new GreedyPlanner, $this->start);

        return [$matchList, $venueList];
    }

    /** @return list<Venue> */
    private function addVenues(int $count): array
    {
        $venues = [];
        for ($i = 1; $i <= $count; $i++) {
            $venues[] = $this->schedule->addVenue(VenueId::generate(), "Стол $i");
        }

        return $venues;
    }

    /** @return list<string> */
    private function times(): array
    {
        return array_map(static fn (Assignment $a) => $a->startsAt->format('H:i'), $this->schedule->assignmentsInOrder());
    }
}
