<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Application\Exception\StageAlreadyGenerated;
use Tournaments\Tournament\Domain\Stage\Format\GroupSplitter;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageFormat;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageRepository;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class GenerateGroupsHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private StageRepository $stages,
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
            if ($this->stages->ofTournament($tournamentId) !== []) {
                throw new StageAlreadyGenerated('The tournament already has a stage; discard it first');
            }

            // Разводим до старта: старт замораживает состав, а посев нужен именно исходный.
            $split = $this->splitter->split($tournament->seededParticipantIds(), $command->groups);
            $tournament->start($now);

            $stages = [];
            foreach ($split as $index => $members) {
                $stage = Stage::generate(
                    id: StageId::generate(),
                    tournamentId: $tournamentId,
                    format: StageFormat::RoundRobin,
                    settings: $settings,
                    seeded: $members,
                    now: $now,
                    name: GroupSplitter::name($index),
                );
                $this->stages->save($stage);
                $stages[] = $stage;
            }
            $this->tournaments->save($tournament);

            return [$tournament, $stages];
        });

        [$tournament, $stages] = $result;
        $this->events->dispatch(...$tournament->releaseEvents());
        foreach ($stages as $stage) {
            $this->events->dispatch(...$stage->releaseEvents());
        }

        return array_map(static fn (Stage $stage) => $stage->id->value, $stages);
    }
}
