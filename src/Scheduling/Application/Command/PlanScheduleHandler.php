<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

use Tournaments\Scheduling\Domain\Exception\NothingToPlan;
use Tournaments\Scheduling\Domain\Exception\ScheduleNotFound;
use Tournaments\Scheduling\Domain\Planning\PlannableMatches;
use Tournaments\Scheduling\Domain\Planning\Planner;
use Tournaments\Scheduling\Domain\Schedule;
use Tournaments\Scheduling\Domain\ScheduleRepository;
use Tournaments\Scheduling\Domain\TournamentRef;
use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;

final readonly class PlanScheduleHandler
{
    use ScheduleCommandSupport;

    public function __construct(
        private ScheduleRepository $schedules,
        private PlannableMatches $matches,
        private Planner $planner,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function __invoke(PlanSchedule $command): void
    {
        $matches = $this->matches->of(TournamentRef::fromString($command->tournamentId));
        if ($matches === []) {
            throw new NothingToPlan('Сначала сгенерируйте сетку: планировать нечего');
        }

        $schedule = $this->transaction->transactional(function () use ($command, $matches): Schedule {
            $schedule = $this->existing($this->schedules, $command->tournamentId)
                ?? throw new ScheduleNotFound('Сначала добавьте хотя бы одну площадку');
            $schedule->plan($matches, $this->planner, $this->clock->now());
            $this->schedules->save($schedule);

            return $schedule;
        });
        $this->events->dispatch(...$schedule->releaseEvents());
    }
}
