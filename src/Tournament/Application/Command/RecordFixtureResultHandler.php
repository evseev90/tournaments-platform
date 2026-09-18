<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Tournament\Domain\Scoring\ScoringRulesFactory;
use Tournaments\Tournament\Domain\Stage\FixtureId;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageRepository;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class RecordFixtureResultHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private StageRepository $stages,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function __invoke(RecordFixtureResult $command): void
    {
        $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
        $stage = $this->stages->get(StageId::fromString($command->stageId));

        $stage->recordResult(
            FixtureId::fromString($command->fixtureId),
            ScoringRulesFactory::outcomeFromArray($command->outcome),
            $tournament->rules,
            $this->clock->now(),
        );

        $this->stages->save($stage);
        $this->events->dispatch(...$stage->releaseEvents());
    }
}
