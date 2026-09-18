<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Application\Exception\StageAlreadyGenerated;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageFormat;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageRepository;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

/**
 * Генерирует сетку и запускает турнир: с этого момента состав заморожен.
 * Пока у турнира одна стадия; группы + плей-офф появятся как вторая стадия позже.
 */
final readonly class GenerateStageHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private StageRepository $stages,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /** @return string идентификатор стадии */
    public function __invoke(GenerateStage $command): string
    {
        $tournamentId = TournamentId::fromString($command->tournamentId);
        $tournament = $this->tournaments->get($tournamentId);

        if ($this->stages->ofTournament($tournamentId) !== []) {
            throw new StageAlreadyGenerated('The tournament already has a stage; discard it first');
        }

        $now = $this->clock->now();
        $tournament->start($now);
        $stage = Stage::generate(
            id: StageId::generate(),
            tournamentId: $tournamentId,
            format: StageFormat::from($command->format),
            settings: StageSettings::fromArray($command->settings),
            seeded: $tournament->seededParticipantIds(),
            now: $now,
        );

        $this->transaction->transactional(function () use ($tournament, $stage): void {
            $this->tournaments->save($tournament);
            $this->stages->save($stage);
        });
        $this->events->dispatch(...$tournament->releaseEvents(), ...$stage->releaseEvents());

        return $stage->id->value;
    }
}
