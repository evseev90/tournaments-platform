<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

final readonly class RenameTournament
{
    public function __construct(
        public string $tournamentId,
        public string $name,
    ) {}
}
