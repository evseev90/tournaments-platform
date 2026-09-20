<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Domain\Planning;

use DateTimeImmutable;
use Tournaments\Scheduling\Domain\Assignment;
use Tournaments\Scheduling\Domain\Exception\NoVenues;
use Tournaments\Scheduling\Domain\ParticipantRef;
use Tournaments\Scheduling\Domain\ScheduleSettings;
use Tournaments\Scheduling\Domain\Venue;

/**
 * Жадная раскладка: матчи идут по порядку раундов, каждый встаёт на самое раннее время,
 * когда свободна площадка, отдохнули оба участника и сыграны матчи, из которых они приходят.
 *
 * Площадка выбирается та, что освободится раньше, поэтому нагрузка расходится по столам сама.
 * Алгоритм не ищет оптимум: он даёт предсказуемое плотное расписание, которое организатор правит руками.
 */
final class GreedyPlanner implements Planner
{
    public function plan(array $matches, array $venues, ScheduleSettings $settings): array
    {
        if ($venues === []) {
            throw new NoVenues('Add at least one venue before planning');
        }

        $ordered = $matches;
        usort($ordered, static fn (PlannableMatch $a, PlannableMatch $b) => [$a->order, $a->sequence] <=> [$b->order, $b->sequence]);

        $venuesByOrder = $venues;
        usort($venuesByOrder, static fn (Venue $a, Venue $b) => $a->order <=> $b->order);

        /** @var array<string, DateTimeImmutable> $venueFree */
        $venueFree = [];
        foreach ($venuesByOrder as $venue) {
            $venueFree[$venue->id->value] = $settings->startsAt;
        }

        /** @var array<string, DateTimeImmutable> $competitorFree */
        $competitorFree = [];
        /** @var array<string, DateTimeImmutable> $matchEnds */
        $matchEnds = [];

        $assignments = [];
        foreach ($ordered as $match) {
            $earliest = $settings->startsAt;

            foreach ($match->dependsOn as $dependency) {
                $end = $matchEnds[$dependency->value] ?? null;
                if ($end !== null) {
                    // Кто бы ни прошёл дальше, он только что играл: даём ему тот же отдых.
                    $earliest = self::latest($earliest, self::plusMinutes($end, $settings->restMinutes));
                }
            }

            foreach ([$match->home, $match->away] as $competitor) {
                $free = $competitor === null ? null : ($competitorFree[$competitor->value] ?? null);
                if ($free !== null) {
                    $earliest = self::latest($earliest, $free);
                }
            }

            $venue = $this->earliestVenue($venuesByOrder, $venueFree, $earliest);
            $startsAt = self::latest($venueFree[$venue->id->value], $earliest);
            $endsAt = self::plusMinutes($startsAt, $settings->matchMinutes);

            $venueFree[$venue->id->value] = self::plusMinutes($endsAt, $settings->breakMinutes);
            $matchEnds[$match->id->value] = $endsAt;
            foreach ([$match->home, $match->away] as $competitor) {
                if ($competitor instanceof ParticipantRef) {
                    $competitorFree[$competitor->value] = self::plusMinutes($endsAt, $settings->restMinutes);
                }
            }

            $assignments[] = new Assignment($match->id, $venue->id, $startsAt, $settings->matchMinutes, $match->home, $match->away);
        }

        return $assignments;
    }

    /**
     * @param  list<Venue>  $venues
     * @param  array<string, DateTimeImmutable>  $venueFree
     */
    private function earliestVenue(array $venues, array $venueFree, DateTimeImmutable $earliest): Venue
    {
        $best = $venues[0];
        $bestStart = self::latest($venueFree[$best->id->value], $earliest);

        foreach ($venues as $venue) {
            $start = self::latest($venueFree[$venue->id->value], $earliest);
            if ($start < $bestStart) {
                $best = $venue;
                $bestStart = $start;
            }
        }

        return $best;
    }

    private static function latest(DateTimeImmutable $a, DateTimeImmutable $b): DateTimeImmutable
    {
        return $a >= $b ? $a : $b;
    }

    private static function plusMinutes(DateTimeImmutable $moment, int $minutes): DateTimeImmutable
    {
        return $minutes === 0 ? $moment : $moment->modify(sprintf('+%d minutes', $minutes));
    }
}
