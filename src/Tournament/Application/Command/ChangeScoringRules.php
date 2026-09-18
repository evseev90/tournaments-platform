<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

final readonly class ChangeScoringRules
{
    /** @param array<string, mixed> $rules */
    public function __construct(
        public string $tournamentId,
        public array $rules,
    ) {}
}
