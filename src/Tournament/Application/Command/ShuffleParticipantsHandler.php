<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Random\Randomizer;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class ShuffleParticipantsHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private Randomizer $randomizer,
    ) {}

    public function __invoke(ShuffleParticipants $command): void
    {
        $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
        $tournament->reorderParticipants(array_values($this->randomizer->shuffleArray($tournament->seededParticipantIds())));
        $this->tournaments->save($tournament);
    }
}
