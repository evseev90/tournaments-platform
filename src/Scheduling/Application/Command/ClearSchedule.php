<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

final readonly class ClearSchedule
{
    public function __construct(
        public string $tournamentId,
    ) {}
}
