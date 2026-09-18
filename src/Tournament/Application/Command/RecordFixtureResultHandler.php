<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Domain\Scoring\ScoringRulesFactory;
use Tournaments\Tournament\Domain\Stage\FixtureId;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageRepository;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class RecordFixtureResultHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private StageRepository $stages,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function __invoke(RecordFixtureResult $command): void
    {
        $outcome = ScoringRulesFactory::outcomeFromArray($command->outcome);

        $stage = $this->transaction->transactional(function () use ($command, $outcome): Stage {
            $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
            $stage = $this->stages->get(StageId::fromString($command->stageId));
            $stage->recordResult(FixtureId::fromString($command->fixtureId), $outcome, $tournament->rules, $this->clock->now());
            $this->stages->save($stage);

            return $stage;
        });
        $this->events->dispatch(...$stage->releaseEvents());
    }
}
