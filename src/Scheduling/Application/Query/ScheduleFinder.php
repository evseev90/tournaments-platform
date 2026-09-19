<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Query;

use DateTimeInterface;
use Tournaments\Scheduling\Domain\Assignment;
use Tournaments\Scheduling\Domain\ScheduleRepository;
use Tournaments\Scheduling\Domain\TournamentRef;
use Tournaments\Scheduling\Domain\Venue;
use Tournaments\Shared\Application\Clock;

final readonly class ScheduleFinder
{
    public function __construct(
        private ScheduleRepository $schedules,
        private Clock $clock,
    ) {}

    public function ofTournament(string $tournamentId): ScheduleView
    {
        $schedule = $this->schedules->ofTournament(TournamentRef::fromString($tournamentId));
        if ($schedule === null) {
            return ScheduleView::empty($this->clock->now()->modify('tomorrow 10:00')->format(DateTimeInterface::ATOM));
        }

        return new ScheduleView(
            venues: array_map(
                static fn (Venue $v) => new VenueView($v->id->value, $v->name, $v->order),
                $schedule->venuesInOrder(),
            ),
            assignments: array_map(
                static fn (Assignment $a) => new AssignmentView(
                    matchId: $a->matchId->value,
                    venueId: $a->venueId->value,
                    startsAt: $a->startsAt->format(DateTimeInterface::ATOM),
                    endsAt: $a->endsAt()->format(DateTimeInterface::ATOM),
                    minutes: $a->minutes,
                ),
                $schedule->assignmentsInOrder(),
            ),
            settings: $schedule->settings->toArray(),
            planned: $schedule->isPlanned(),
        );
    }
}
