<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

final readonly class ReorderParticipants
{
    /** @param list<string> $participantIds все участники в новом порядке посева */
    public function __construct(
        public string $tournamentId,
        public array $participantIds,
    ) {}
}
