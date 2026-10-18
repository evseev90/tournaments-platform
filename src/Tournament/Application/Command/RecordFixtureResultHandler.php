<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Domain\Scoring\ScoringRulesFactory;
use Tournaments\Tournament\Domain\Stage\FixtureId;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class RecordFixtureResultHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function __invoke(RecordFixtureResult $command): void
    {
        $outcome = ScoringRulesFactory::outcomeFromArray($command->outcome);
        $now = $this->clock->now();

        $tournament = $this->transaction->transactional(function () use ($command, $outcome, $now): Tournament {
            $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
            $tournament->recordFixtureResult(
                StageId::fromString($command->stageId),
                FixtureId::fromString($command->fixtureId),
                $outcome,
                $now,
            );
            $this->tournaments->save($tournament);

            return $tournament;
        });

        $this->events->dispatch(...$tournament->releaseEvents());
    }
}
