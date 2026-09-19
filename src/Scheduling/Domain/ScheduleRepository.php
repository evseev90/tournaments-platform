<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Domain;

interface ScheduleRepository
{
    public function ofTournament(TournamentRef $tournament): ?Schedule;

    public function save(Schedule $schedule): void;

    public function remove(Schedule $schedule): void;
}
