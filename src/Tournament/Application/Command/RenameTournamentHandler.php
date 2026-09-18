<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Tournament\Domain\Tournament\Name;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class RenameTournamentHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
    ) {}

    public function __invoke(RenameTournament $command): void
    {
        $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
        $tournament->rename(new Name($command->name));
        $this->tournaments->save($tournament);
    }
}
