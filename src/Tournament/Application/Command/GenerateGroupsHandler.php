<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Domain\Stage\Format\GroupSplitter;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class GenerateGroupsHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
        private GroupSplitter $splitter = new GroupSplitter,
    ) {}

    /** @return list<string> идентификаторы созданных групп */
    public function __invoke(GenerateGroups $command): array
    {
        $settings = new StageSettings(legs: $command->legs);
        $now = $this->clock->now();

        /** @var array{Tournament, list<Stage>} $result */
        $result = $this->transaction->transactional(function () use ($command, $settings, $now): array {
            $tournamentId = TournamentId::fromString($command->tournamentId);
            $tournament = $this->tournaments->get($tournamentId);
            $stages = $tournament->generateGroups($command->groups, $settings, $now, $this->splitter);
            $this->tournaments->save($tournament);

            return [$tournament, $stages];
        });

        [$tournament, $stages] = $result;
        $this->events->dispatch(...$tournament->releaseEvents());

        return array_map(static fn (Stage $stage) => $stage->id->value, $stages);
    }
}
