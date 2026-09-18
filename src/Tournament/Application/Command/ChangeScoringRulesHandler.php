<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Domain\Scoring\ScoringRulesFactory;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class ChangeScoringRulesHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private TransactionManager $transaction,
    ) {}

    public function __invoke(ChangeScoringRules $command): void
    {
        $this->transaction->transactional(function () use ($command): void {
            $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
            $tournament->changeRules(ScoringRulesFactory::rulesFromArray($command->rules));
            $this->tournaments->save($tournament);
        });
    }
}
