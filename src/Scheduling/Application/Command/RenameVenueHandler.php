<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

use Tournaments\Scheduling\Domain\Exception\ScheduleNotFound;
use Tournaments\Scheduling\Domain\ScheduleRepository;
use Tournaments\Scheduling\Domain\VenueId;
use Tournaments\Shared\Application\TransactionManager;

final readonly class RenameVenueHandler
{
    use ScheduleCommandSupport;

    public function __construct(
        private ScheduleRepository $schedules,
        private TransactionManager $transaction,
    ) {}

    public function __invoke(RenameVenue $command): void
    {
        $this->transaction->transactional(function () use ($command): void {
            $schedule = $this->existing($this->schedules, $command->tournamentId)
                ?? throw new ScheduleNotFound('Расписание ещё не заведено');
            $schedule->renameVenue(VenueId::fromString($command->venueId), $command->name);
            $this->schedules->save($schedule);
        });
    }
}
