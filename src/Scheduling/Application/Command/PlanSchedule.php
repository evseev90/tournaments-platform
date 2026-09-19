<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

final readonly class PlanSchedule
{
    public function __construct(
        public string $tournamentId,
    ) {}
}
