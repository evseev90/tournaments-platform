<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Domain\Tournament\OwnerId;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class ClaimTournamentHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private TransactionManager $transaction,
    ) {}

    public function __invoke(ClaimTournament $command): void
    {
        $this->transaction->transactional(function () use ($command): void {
            $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
            $tournament->claimBy(new OwnerId($command->ownerId));
            $this->tournaments->save($tournament);
        });
    }
}
