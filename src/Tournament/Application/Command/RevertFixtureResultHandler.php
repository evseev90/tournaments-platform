<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Domain\Exception\TournamentNotFound;
use Tournaments\Tournament\Domain\Stage\FixtureId;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class RevertFixtureResultHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function __invoke(RevertFixtureResult $command): void
    {
        $now = $this->clock->now();

        $tournament = $this->transaction->transactional(function () use ($command, $now): Tournament {
            $stageId = StageId::fromString($command->stageId);
            $tournament = $this->tournaments->findByStageId($stageId)
                ?? throw new TournamentNotFound(sprintf('Tournament for stage %s not found', $stageId));

            $tournament->revertFixtureResult($stageId, FixtureId::fromString($command->fixtureId), $now);
            $this->tournaments->save($tournament);

            return $tournament;
        });

        $this->events->dispatch(...$tournament->releaseEvents());
    }
}
