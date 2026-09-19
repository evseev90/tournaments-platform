<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

use Tournaments\Scheduling\Domain\ScheduleRepository;
use Tournaments\Scheduling\Domain\VenueId;
use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\TransactionManager;

final readonly class AddVenueHandler
{
    use ScheduleCommandSupport;

    public function __construct(
        private ScheduleRepository $schedules,
        private TransactionManager $transaction,
        private Clock $clock,
    ) {}

    public function __invoke(AddVenue $command): string
    {
        $id = VenueId::generate();

        $this->transaction->transactional(function () use ($command, $id): void {
            $schedule = $this->ensure($this->schedules, $this->clock, $command->tournamentId);
            $schedule->addVenue($id, $command->name);
            $this->schedules->save($schedule);
        });

        return $id->value;
    }
}
