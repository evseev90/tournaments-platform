<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Domain\Stage\Format\SwissPairing;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class PairNextRoundHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
        private SwissPairing $pairing = new SwissPairing,
    ) {}

    public function __invoke(PairNextRound $command): void
    {
        $now = $this->clock->now();

        $tournament = $this->transaction->transactional(function () use ($command, $now): Tournament {
            $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
            $tournament->pairNextRound(StageId::fromString($command->stageId), $now, $this->pairing);
            $this->tournaments->save($tournament);

            return $tournament;
        });

        $this->events->dispatch(...$tournament->releaseEvents());
    }
}
