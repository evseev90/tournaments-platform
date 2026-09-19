<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Application\Exception\GroupsNotFinished;
use Tournaments\Tournament\Application\Exception\NoStageGenerated;
use Tournaments\Tournament\Application\Exception\StageAlreadyGenerated;
use Tournaments\Tournament\Domain\Stage\Format\PlayoffSeeder;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageFormat;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageRepository;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Stage\StandingsCalculator;
use Tournaments\Tournament\Domain\Tournament\TournamentId;

final readonly class GeneratePlayoffHandler
{
    public const int PHASE = 2;

    public function __construct(
        private StageRepository $stages,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
        private PlayoffSeeder $seeder = new PlayoffSeeder,
        private StandingsCalculator $standings = new StandingsCalculator,
    ) {}

    public function __invoke(GeneratePlayoff $command): string
    {
        $now = $this->clock->now();

        $stage = $this->transaction->transactional(function () use ($command, $now): Stage {
            $tournamentId = TournamentId::fromString($command->tournamentId);
            $existing = $this->stages->ofTournament($tournamentId);

            $groups = array_values(array_filter($existing, static fn (Stage $s) => $s->phase === 1));
            if ($groups === []) {
                throw new NoStageGenerated('Сначала сыграйте групповой этап');
            }
            if (array_any($existing, static fn (Stage $s) => $s->phase >= self::PHASE)) {
                throw new StageAlreadyGenerated('Плей-офф уже сформирован');
            }
            if (array_any($groups, static fn (Stage $s) => ! $s->isCompleted())) {
                throw new GroupsNotFinished('Плей-офф можно собрать, только когда сыграны все матчи групп');
            }

            $tables = array_map(
                fn (Stage $group) => $this->standings->calculate($group->participants(), $group->fixtures()),
                $groups,
            );

            $stage = Stage::generate(
                id: StageId::generate(),
                tournamentId: $tournamentId,
                format: StageFormat::SingleElimination,
                settings: new StageSettings(thirdPlaceMatch: $command->thirdPlaceMatch),
                seeded: $this->seeder->seed($tables, $command->advancePerGroup),
                now: $now,
                name: 'Плей-офф',
                phase: self::PHASE,
            );
            $this->stages->save($stage);

            return $stage;
        });
        $this->events->dispatch(...$stage->releaseEvents());

        return $stage->id->value;
    }
}
