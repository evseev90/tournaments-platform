<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

final readonly class ChangeScheduleSettings
{
    /** @param array<string, mixed> $settings см. ScheduleSettings::fromArray */
    public function __construct(
        public string $tournamentId,
        public array $settings,
    ) {}
}
