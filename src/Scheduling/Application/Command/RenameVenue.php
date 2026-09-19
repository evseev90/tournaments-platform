<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

final readonly class RenameVenue
{
    public function __construct(
        public string $tournamentId,
        public string $venueId,
        public string $name,
    ) {}
}
