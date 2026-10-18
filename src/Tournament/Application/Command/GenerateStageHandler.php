<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageFormat;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class GenerateStageHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /** @return string идентификатор стадии */
    public function __invoke(GenerateStage $command): string
    {
        $format = StageFormat::from($command->format);
        $settings = StageSettings::fromArray($command->settings);
        $now = $this->clock->now();

        /** @var array{Tournament, Stage} $result */
        $result = $this->transaction->transactional(function () use ($command, $format, $settings, $now): array {
            $tournamentId = TournamentId::fromString($command->tournamentId);
            $tournament = $this->tournaments->get($tournamentId);
            $stage = $tournament->generateStage(StageId::generate(), $format, $settings, $now);
            $this->tournaments->save($tournament);

            return [$tournament, $stage];
        });

        [$tournament, $stage] = $result;
        $this->events->dispatch(...$tournament->releaseEvents());

        return $stage->id->value;
    }
}
