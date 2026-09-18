<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

final readonly class RemoveParticipant
{
    public function __construct(
        public string $tournamentId,
        public string $participantId,
    ) {}
}
