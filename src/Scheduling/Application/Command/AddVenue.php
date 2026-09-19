<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

final readonly class AddVenue
{
    public function __construct(
        public string $tournamentId,
        public string $name,
    ) {}
}
