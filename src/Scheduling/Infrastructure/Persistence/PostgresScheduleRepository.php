<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use stdClass;
use Tournaments\Scheduling\Domain\Assignment;
use Tournaments\Scheduling\Domain\MatchId;
use Tournaments\Scheduling\Domain\ParticipantRef;
use Tournaments\Scheduling\Domain\Schedule;
use Tournaments\Scheduling\Domain\ScheduleId;
use Tournaments\Scheduling\Domain\ScheduleRepository;
use Tournaments\Scheduling\Domain\ScheduleSettings;
use Tournaments\Scheduling\Domain\TournamentRef;
use Tournaments\Scheduling\Domain\Venue;
use Tournaments\Scheduling\Domain\VenueId;
use Tournaments\Tournament\Infrastructure\Persistence\Json;

/** Агрегат целиком: строка расписания, площадки и назначения. Сохранение перезаписывает назначения полностью. */
final readonly class PostgresScheduleRepository implements ScheduleRepository
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function ofTournament(TournamentRef $tournament): ?Schedule
    {
        $row = $this->schedules()->where('tournament_id', $tournament->value)->lockForUpdate()->first();

        return $row instanceof stdClass ? $this->hydrate($row) : null;
    }

    public function save(Schedule $schedule): void
    {
        $now = (new DateTimeImmutable)->format(DATE_ATOM);

        $this->schedules()->upsert([[
            'id' => $schedule->id->value,
            'tournament_id' => $schedule->tournament->value,
            'settings' => json_encode($schedule->settings->toArray(), JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['id'], ['settings', 'updated_at']);

        $venues = $schedule->venuesInOrder();
        $venueIds = array_map(static fn (Venue $v) => $v->id->value, $venues);
        $this->venues()->where('schedule_id', $schedule->id->value)->whereNotIn('id', $venueIds)->delete();

        if ($venues !== []) {
            $this->venues()->upsert(array_map(static fn (Venue $v) => [
                'id' => $v->id->value,
                'schedule_id' => $schedule->id->value,
                'name' => $v->name,
                'position' => $v->order,
            ], $venues), ['id'], ['name', 'position']);
        }

        // Назначения проще переписать целиком: их немного, а порядок и состав меняются пачкой.
        $this->assignments()->where('schedule_id', $schedule->id->value)->delete();
        $assignments = $schedule->assignmentsInOrder();
        if ($assignments !== []) {
            $this->assignments()->insert(array_map(static fn (Assignment $a) => [
                'schedule_id' => $schedule->id->value,
                'match_id' => $a->matchId->value,
                'venue_id' => $a->venueId->value,
                'starts_at' => $a->startsAt->format(DATE_ATOM),
                'minutes' => $a->minutes,
                'home_id' => $a->home?->value,
                'away_id' => $a->away?->value,
            ], $assignments));
        }
    }

    public function remove(Schedule $schedule): void
    {
        $this->schedules()->where('id', $schedule->id->value)->delete();
    }

    private function hydrate(stdClass $row): Schedule
    {
        $venues = $this->venues()
            ->where('schedule_id', $row->id)
            ->orderBy('position')
            ->get()
            ->map(static fn (stdClass $v) => new Venue(VenueId::fromString($v->id), $v->name, (int) $v->position))
            ->all();

        $assignments = $this->assignments()
            ->where('schedule_id', $row->id)
            ->orderBy('starts_at')
            ->get()
            ->map(static fn (stdClass $a) => new Assignment(
                matchId: MatchId::fromString($a->match_id),
                venueId: VenueId::fromString($a->venue_id),
                startsAt: new DateTimeImmutable($a->starts_at),
                minutes: (int) $a->minutes,
                home: $a->home_id === null ? null : ParticipantRef::fromString($a->home_id),
                away: $a->away_id === null ? null : ParticipantRef::fromString($a->away_id),
            ))
            ->all();

        return Schedule::reconstitute(
            id: ScheduleId::fromString($row->id),
            tournament: TournamentRef::fromString($row->tournament_id),
            settings: ScheduleSettings::fromArray(Json::map($row->settings)),
            venues: array_values($venues),
            assignments: array_values($assignments),
        );
    }

    private function schedules(): Builder
    {
        return $this->db->table('schedules');
    }

    private function venues(): Builder
    {
        return $this->db->table('venues');
    }

    private function assignments(): Builder
    {
        return $this->db->table('schedule_assignments');
    }
}
