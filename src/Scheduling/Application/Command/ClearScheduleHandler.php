<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

use Tournaments\Scheduling\Domain\Exception\ScheduleNotFound;
use Tournaments\Scheduling\Domain\Schedule;
use Tournaments\Scheduling\Domain\ScheduleRepository;
use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;

final readonly class ClearScheduleHandler
{
    use ScheduleCommandSupport;

    public function __construct(
        private ScheduleRepository $schedules,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function __invoke(ClearSchedule $command): void
    {
        $schedule = $this->transaction->transactional(function () use ($command): Schedule {
            $schedule = $this->existing($this->schedules, $command->tournamentId)
                ?? throw new ScheduleNotFound('Расписание ещё не составлено');
            $schedule->clear($this->clock->now());
            $this->schedules->save($schedule);

            return $schedule;
        });
        $this->events->dispatch(...$schedule->releaseEvents());
    }
}
