<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Application\Exception\NoStageGenerated;
use Tournaments\Tournament\Application\Exception\StageHasResults;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\StageRepository;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class DiscardStageHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private StageRepository $stages,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function __invoke(DiscardStage $command): void
    {
        $tournament = $this->transaction->transactional(function () use ($command): Tournament {
            $tournamentId = TournamentId::fromString($command->tournamentId);
            $tournament = $this->tournaments->get($tournamentId);
            $stages = $this->stages->ofTournament($tournamentId);
            if ($stages === []) {
                throw new NoStageGenerated('The tournament has no stage to discard');
            }
            foreach ($stages as $stage) {
                if (array_any($stage->fixtures(), static fn (Fixture $f) => $f->isFinished())) {
                    throw new StageHasResults('Cannot discard a stage with recorded results; revert them first');
                }
                $this->stages->remove($stage);
            }
            $tournament->reopen($this->clock->now());
            $this->tournaments->save($tournament);

            return $tournament;
        });
        $this->events->dispatch(...$tournament->releaseEvents());
    }
}
