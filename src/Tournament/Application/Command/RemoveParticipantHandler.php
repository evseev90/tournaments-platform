<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class RemoveParticipantHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function __invoke(RemoveParticipant $command): void
    {
        $tournament = $this->transaction->transactional(function () use ($command): Tournament {
            $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
            $tournament->removeParticipant(ParticipantId::fromString($command->participantId), $this->clock->now());
            $this->tournaments->save($tournament);

            return $tournament;
        });
        $this->events->dispatch(...$tournament->releaseEvents());
    }
}
