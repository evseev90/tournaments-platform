<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class ReorderParticipantsHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private TransactionManager $transaction,
    ) {}

    public function __invoke(ReorderParticipants $command): void
    {
        $this->transaction->transactional(function () use ($command): void {
            $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
            $tournament->reorderParticipants(array_map(ParticipantId::fromString(...), $command->participantIds));
            $this->tournaments->save($tournament);
        });
    }
}
