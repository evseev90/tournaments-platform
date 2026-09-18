<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Tournament\Domain\Scoring\ScoringRulesFactory;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class ChangeScoringRulesHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
    ) {}

    public function __invoke(ChangeScoringRules $command): void
    {
        $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
        $tournament->changeRules(ScoringRulesFactory::rulesFromArray($command->rules));
        $this->tournaments->save($tournament);
    }
}
