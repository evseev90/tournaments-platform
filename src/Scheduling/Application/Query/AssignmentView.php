<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Query;

final readonly class AssignmentView
{
    public function __construct(
        public string $matchId,
        public string $venueId,
        public string $startsAt,
        public string $endsAt,
        public int $minutes,
    ) {}
}
