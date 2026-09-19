<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

final readonly class MoveMatch
{
    public function __construct(
        public string $tournamentId,
        public string $matchId,
        public string $venueId,
        public string $startsAt,
    ) {}
}
