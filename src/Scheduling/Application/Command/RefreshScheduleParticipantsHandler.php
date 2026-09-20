<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

use Tournaments\Scheduling\Domain\Planning\PlannableMatches;
use Tournaments\Scheduling\Domain\ScheduleRepository;
use Tournaments\Scheduling\Domain\TournamentRef;
use Tournaments\Shared\Application\TransactionManager;

final readonly class RefreshScheduleParticipantsHandler
{
    use ScheduleCommandSupport;

    public function __construct(
        private ScheduleRepository $schedules,
        private PlannableMatches $matches,
        private TransactionManager $transaction,
    ) {}

    public function __invoke(RefreshScheduleParticipants $command): void
    {
        $schedule = $this->existing($this->schedules, $command->tournamentId);
        if ($schedule === null || ! $schedule->isPlanned()) {
            return;
        }

        $matches = $this->matches->of(TournamentRef::fromString($command->tournamentId));

        $this->transaction->transactional(function () use ($command, $matches): void {
            $schedule = $this->existing($this->schedules, $command->tournamentId);
            $schedule?->refreshParticipants($matches);
            if ($schedule !== null) {
                $this->schedules->save($schedule);
            }
        });
    }
}
