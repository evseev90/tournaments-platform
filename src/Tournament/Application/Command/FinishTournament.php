<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

final readonly class FinishTournament
{
    public function __construct(
        public string $tournamentId,
    ) {}
}
