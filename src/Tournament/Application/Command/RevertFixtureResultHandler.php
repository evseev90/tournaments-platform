<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Domain\Stage\FixtureId;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageRepository;

final readonly class RevertFixtureResultHandler
{
    public function __construct(
        private StageRepository $stages,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function __invoke(RevertFixtureResult $command): void
    {
        $stage = $this->transaction->transactional(function () use ($command): Stage {
            $stage = $this->stages->get(StageId::fromString($command->stageId));
            $stage->revertResult(FixtureId::fromString($command->fixtureId), $this->clock->now());
            $this->stages->save($stage);

            return $stage;
        });
        $this->events->dispatch(...$stage->releaseEvents());
    }
}
