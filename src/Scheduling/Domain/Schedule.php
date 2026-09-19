<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Domain;

use DateTimeImmutable;
use Tournaments\Scheduling\Domain\Event\MatchMoved;
use Tournaments\Scheduling\Domain\Event\ScheduleCleared;
use Tournaments\Scheduling\Domain\Event\SchedulePlanned;
use Tournaments\Scheduling\Domain\Exception\CompetitorBusy;
use Tournaments\Scheduling\Domain\Exception\MatchNotScheduled;
use Tournaments\Scheduling\Domain\Exception\VenueBusy;
use Tournaments\Scheduling\Domain\Exception\VenueNotFound;
use Tournaments\Scheduling\Domain\Planning\PlannableMatch;
use Tournaments\Scheduling\Domain\Planning\Planner;
use Tournaments\Shared\Domain\RecordsEvents;

/**
 * Агрегат «Расписание»: площадки турнира, параметры раскладки и назначения матчей.
 * Один агрегат на турнир, потому что проверка «площадка занята» и «участник занят»
 * требует видеть все назначения сразу.
 */
final class Schedule
{
    use RecordsEvents;

    /** @var array<string, Venue> */
    private array $venues = [];

    /** @var array<string, Assignment> */
    private array $assignments = [];

    /**
     * @param  list<Venue>  $venues
     * @param  list<Assignment>  $assignments
     */
    private function __construct(
        public readonly ScheduleId $id,
        public readonly TournamentRef $tournament,
        public private(set) ScheduleSettings $settings,
        array $venues,
        array $assignments,
    ) {
        foreach ($venues as $venue) {
            $this->venues[$venue->id->value] = $venue;
        }
        foreach ($assignments as $assignment) {
            $this->assignments[$assignment->matchId->value] = $assignment;
        }
    }

    public static function create(ScheduleId $id, TournamentRef $tournament, ScheduleSettings $settings): self
    {
        return new self($id, $tournament, $settings, [], []);
    }

    /**
     * @param  list<Venue>  $venues
     * @param  list<Assignment>  $assignments
     */
    public static function reconstitute(
        ScheduleId $id,
        TournamentRef $tournament,
        ScheduleSettings $settings,
        array $venues,
        array $assignments,
    ): self {
        return new self($id, $tournament, $settings, $venues, $assignments);
    }

    public function changeSettings(ScheduleSettings $settings): void
    {
        $this->settings = $settings;
    }

    public function addVenue(VenueId $id, string $name): Venue
    {
        $venue = new Venue($id, $name, count($this->venues) + 1);
        $this->venues[$id->value] = $venue;

        return $venue;
    }

    public function renameVenue(VenueId $id, string $name): void
    {
        $this->venue($id)->rename($name);
    }

    /** Вместе с площадкой снимаются назначенные на неё матчи: они возвращаются в «без времени». */
    public function removeVenue(VenueId $id): void
    {
        $this->venue($id);
        unset($this->venues[$id->value]);

        foreach ($this->assignments as $key => $assignment) {
            if ($assignment->venueId->equals($id)) {
                unset($this->assignments[$key]);
            }
        }

        $order = 1;
        foreach ($this->venuesInOrder() as $venue) {
            $venue->reorder($order++);
        }
    }

    /**
     * Полная перекладка. Ручные правки при этом теряются, поэтому вызывается только явно.
     *
     * @param  list<PlannableMatch>  $matches
     */
    public function plan(array $matches, Planner $planner, DateTimeImmutable $now): void
    {
        $this->assignments = [];
        foreach ($planner->plan($matches, $this->venuesInOrder(), $this->settings) as $assignment) {
            $this->assignments[$assignment->matchId->value] = $assignment;
        }

        $this->recordEvent(new SchedulePlanned($this->tournament, $this->id, count($this->assignments), $now));
    }

    /** Ручной перенос матча. Организатор главнее алгоритма, но двойное бронирование запрещено. */
    public function move(MatchId $matchId, VenueId $venueId, DateTimeImmutable $startsAt, DateTimeImmutable $now): void
    {
        $assignment = $this->assignment($matchId);
        $this->venue($venueId);

        $endsAt = $startsAt->modify(sprintf('+%d minutes', $assignment->minutes));
        $this->assertVenueFree($venueId, $startsAt, $endsAt, $matchId);
        $this->assertCompetitorsFree($assignment, $startsAt, $endsAt);

        $assignment->moveTo($venueId, $startsAt);
        $this->recordEvent(new MatchMoved($this->tournament, $this->id, $matchId, $startsAt, $now));
    }

    public function clear(DateTimeImmutable $now): void
    {
        $this->assignments = [];
        $this->recordEvent(new ScheduleCleared($this->tournament, $this->id, $now));
    }

    /**
     * Подтягивает участников, ставших известными после результатов предыдущих матчей.
     *
     * @param  list<PlannableMatch>  $matches
     */
    public function refreshCompetitors(array $matches): void
    {
        foreach ($matches as $match) {
            $assignment = $this->assignments[$match->id->value] ?? null;
            $assignment?->withCompetitors($match->home, $match->away);
        }
    }

    public function venue(VenueId $id): Venue
    {
        return $this->venues[$id->value] ?? throw new VenueNotFound(sprintf('Venue %s not found', $id));
    }

    public function assignment(MatchId $matchId): Assignment
    {
        return $this->assignments[$matchId->value] ?? throw new MatchNotScheduled(sprintf('Match %s is not on the schedule', $matchId));
    }

    /** @return list<Venue> */
    public function venuesInOrder(): array
    {
        $venues = array_values($this->venues);
        usort($venues, static fn (Venue $a, Venue $b) => $a->order <=> $b->order);

        return $venues;
    }

    /** @return list<Assignment> */
    public function assignmentsInOrder(): array
    {
        $assignments = array_values($this->assignments);
        usort($assignments, fn (Assignment $a, Assignment $b) => [$a->startsAt, $this->venueOrder($a)] <=> [$b->startsAt, $this->venueOrder($b)]);

        return $assignments;
    }

    public function isPlanned(): bool
    {
        return $this->assignments !== [];
    }

    private function venueOrder(Assignment $assignment): int
    {
        return isset($this->venues[$assignment->venueId->value]) ? $this->venues[$assignment->venueId->value]->order : 0;
    }

    private function assertVenueFree(VenueId $venueId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, MatchId $moving): void
    {
        foreach ($this->assignments as $other) {
            if ($other->matchId->equals($moving) || ! $other->venueId->equals($venueId)) {
                continue;
            }
            if ($other->overlaps($startsAt, $endsAt)) {
                throw new VenueBusy('Площадка занята другим матчем в это время');
            }
        }
    }

    private function assertCompetitorsFree(Assignment $moving, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): void
    {
        foreach ($this->assignments as $other) {
            if ($other->matchId->equals($moving->matchId)) {
                continue;
            }
            if (! $other->overlaps($startsAt, $endsAt)) {
                continue;
            }
            if ($other->involves($moving->home) || $other->involves($moving->away)) {
                throw new CompetitorBusy('Участник в это время уже играет другой матч');
            }
        }
    }
}
