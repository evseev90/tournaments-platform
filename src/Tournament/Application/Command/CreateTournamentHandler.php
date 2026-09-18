<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Tournament\Domain\Scoring\ScoringRulesFactory;
use Tournaments\Tournament\Domain\Scoring\Sport;
use Tournaments\Tournament\Domain\Tournament\Name;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class CreateTournamentHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function __invoke(CreateTournament $command): CreatedTournament
    {
        $tournament = Tournament::create(
            id: TournamentId::generate(),
            name: new Name($command->name),
            sport: Sport::from($command->sport),
            rules: $command->rules === null ? null : ScoringRulesFactory::rulesFromArray($command->rules),
            now: $this->clock->now(),
        );

        $this->tournaments->save($tournament);
        $this->events->dispatch(...$tournament->releaseEvents());

        return new CreatedTournament($tournament->id->value, $tournament->adminToken->value, $tournament->publicSlug->value);
    }
}
