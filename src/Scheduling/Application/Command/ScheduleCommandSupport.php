<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

use Tournaments\Scheduling\Domain\Schedule;
use Tournaments\Scheduling\Domain\ScheduleId;
use Tournaments\Scheduling\Domain\ScheduleRepository;
use Tournaments\Scheduling\Domain\ScheduleSettings;
use Tournaments\Scheduling\Domain\TournamentRef;
use Tournaments\Shared\Application\Clock;

/**
 * Расписание появляется само при первом действии организатора: отдельного «создать расписание» нет,
 * потому что для пользователя это не событие, а побочный эффект добавления первой площадки.
 */
trait ScheduleCommandSupport
{
    private function existing(ScheduleRepository $schedules, string $tournamentId): ?Schedule
    {
        return $schedules->ofTournament(TournamentRef::fromString($tournamentId));
    }

    private function ensure(ScheduleRepository $schedules, Clock $clock, string $tournamentId): Schedule
    {
        $tournament = TournamentRef::fromString($tournamentId);

        return $schedules->ofTournament($tournament) ?? Schedule::create(
            ScheduleId::generate(),
            $tournament,
            new ScheduleSettings(startsAt: $clock->now()->modify('tomorrow 10:00')),
        );
    }
}
