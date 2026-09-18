<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class FinishTournamentHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function __invoke(FinishTournament $command): void
    {
        $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
        $tournament->finish($this->clock->now());
        $this->tournaments->save($tournament);
        $this->events->dispatch(...$tournament->releaseEvents());
    }
}
