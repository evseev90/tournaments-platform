<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

use DateTimeImmutable;
use Tournaments\Scheduling\Domain\Exception\ScheduleNotFound;
use Tournaments\Scheduling\Domain\MatchId;
use Tournaments\Scheduling\Domain\Schedule;
use Tournaments\Scheduling\Domain\ScheduleRepository;
use Tournaments\Scheduling\Domain\VenueId;
use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;

final readonly class MoveMatchHandler
{
    use ScheduleCommandSupport;

    public function __construct(
        private ScheduleRepository $schedules,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function __invoke(MoveMatch $command): void
    {
        $startsAt = new DateTimeImmutable($command->startsAt);

        $schedule = $this->transaction->transactional(function () use ($command, $startsAt): Schedule {
            $schedule = $this->existing($this->schedules, $command->tournamentId)
                ?? throw new ScheduleNotFound('Расписание ещё не составлено');
            $schedule->move(MatchId::fromString($command->matchId), VenueId::fromString($command->venueId), $startsAt, $this->clock->now());
            $this->schedules->save($schedule);

            return $schedule;
        });
        $this->events->dispatch(...$schedule->releaseEvents());
    }
}
