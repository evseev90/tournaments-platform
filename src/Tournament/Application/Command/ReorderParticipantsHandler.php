<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class ReorderParticipantsHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
    ) {}

    public function __invoke(ReorderParticipants $command): void
    {
        $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
        $tournament->reorderParticipants(array_map(ParticipantId::fromString(...), $command->participantIds));
        $this->tournaments->save($tournament);
    }
}
