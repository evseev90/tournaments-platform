<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Tournament\Domain\Stage\Format\PlayoffSeeder;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class GeneratePlayoffHandler
{
    public const int PHASE = 2;

    public function __construct(
        private TournamentRepository $tournaments,
        private TransactionManager $transaction,
        private EventDispatcher $events,
        private Clock $clock,
        private PlayoffSeeder $seeder = new PlayoffSeeder,
    ) {}

    public function __invoke(GeneratePlayoff $command): string
    {
        $now = $this->clock->now();

        /** @var array{Tournament, Stage} $result */
        $result = $this->transaction->transactional(function () use ($command, $now): array {
            $tournamentId = TournamentId::fromString($command->tournamentId);
            $tournament = $this->tournaments->get($tournamentId);
            $stage = $tournament->generatePlayoff($command->advancePerGroup, $command->thirdPlaceMatch, $now, $this->seeder);
            $this->tournaments->save($tournament);

            return [$tournament, $stage];
        });

        [$tournament, $stage] = $result;
        $this->events->dispatch(...$tournament->releaseEvents());

        return $stage->id->value;
    }
}
