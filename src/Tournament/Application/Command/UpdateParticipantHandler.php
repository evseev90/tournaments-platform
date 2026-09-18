<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Domain\Tournament\Name;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class UpdateParticipantHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private TransactionManager $transaction,
    ) {}

    public function __invoke(UpdateParticipant $command): void
    {
        $this->transaction->transactional(function () use ($command): void {
            $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
            $tournament->updateParticipant(ParticipantId::fromString($command->participantId), new Name($command->name), $command->members);
            $this->tournaments->save($tournament);
        });
    }
}
