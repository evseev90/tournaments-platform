<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

final readonly class GenerateStage
{
    /** @param array<string, mixed> $settings см. StageSettings::fromArray */
    public function __construct(
        public string $tournamentId,
        public string $format,
        public array $settings = [],
    ) {}
}
