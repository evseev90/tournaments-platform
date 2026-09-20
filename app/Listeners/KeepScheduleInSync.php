<?php

declare(strict_types=1);

namespace App\Listeners;

use Tournaments\Scheduling\Application\Command\ClearSchedule;
use Tournaments\Scheduling\Application\Command\ClearScheduleHandler;
use Tournaments\Scheduling\Application\Command\RefreshScheduleParticipants;
use Tournaments\Scheduling\Application\Command\RefreshScheduleParticipantsHandler;
use Tournaments\Scheduling\Domain\Exception\ScheduleNotFound;
use Tournaments\Tournament\Domain\Event\FixtureResultRecorded;
use Tournaments\Tournament\Domain\Event\FixtureResultReverted;
use Tournaments\Tournament\Domain\Event\TournamentReopened;

/**
 * Связь двух контекстов: «Расписание» слушает «Турнир».
 * Результат матча делает известными участников следующих, а отмена сетки обнуляет назначения.
 */
final readonly class KeepScheduleInSync
{
    public function __construct(
        private RefreshScheduleParticipantsHandler $refresh,
        private ClearScheduleHandler $clear,
    ) {}

    public function onResultRecorded(FixtureResultRecorded $event): void
    {
        ($this->refresh)(new RefreshScheduleParticipants($event->tournamentId->value));
    }

    public function onResultReverted(FixtureResultReverted $event): void
    {
        ($this->refresh)(new RefreshScheduleParticipants($event->tournamentId->value));
    }

    public function onTournamentReopened(TournamentReopened $event): void
    {
        try {
            ($this->clear)(new ClearSchedule($event->tournamentId->value));
        } catch (ScheduleNotFound) {
            // Расписания не было, чистить нечего.
        }
    }
}
