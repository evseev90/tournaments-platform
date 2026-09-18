<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

final readonly class UpdateParticipant
{
    /** @param list<string> $members */
    public function __construct(
        public string $tournamentId,
        public string $participantId,
        public string $name,
        public array $members = [],
    ) {}
}
