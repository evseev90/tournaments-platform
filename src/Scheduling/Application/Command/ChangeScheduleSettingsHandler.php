<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

use Tournaments\Scheduling\Domain\ScheduleRepository;
use Tournaments\Scheduling\Domain\ScheduleSettings;
use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\TransactionManager;

final readonly class ChangeScheduleSettingsHandler
{
    use ScheduleCommandSupport;

    public function __construct(
        private ScheduleRepository $schedules,
        private TransactionManager $transaction,
        private Clock $clock,
    ) {}

    public function __invoke(ChangeScheduleSettings $command): void
    {
        $settings = ScheduleSettings::fromArray($command->settings);

        $this->transaction->transactional(function () use ($command, $settings): void {
            $schedule = $this->ensure($this->schedules, $this->clock, $command->tournamentId);
            $schedule->changeSettings($settings);
            $this->schedules->save($schedule);
        });
    }
}
