<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Domain\Stage\Format\SwissPairing;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageRepository;

final readonly class PairNextRoundHandler
{
    public function __construct(
        private StageRepository $stages,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
        private SwissPairing $pairing = new SwissPairing,
    ) {}

    public function __invoke(PairNextRound $command): void
    {
        $stage = $this->transaction->transactional(function () use ($command): Stage {
            $stage = $this->stages->get(StageId::fromString($command->stageId));
            $stage->pairNextRound($this->pairing, $this->clock->now());
            $this->stages->save($stage);

            return $stage;
        });
        $this->events->dispatch(...$stage->releaseEvents());
    }
}
