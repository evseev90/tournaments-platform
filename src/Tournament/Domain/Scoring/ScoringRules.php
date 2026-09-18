<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Scoring;

use Tournaments\Tournament\Domain\Exception\InvalidOutcome;

interface ScoringRules
{
    /** @throws InvalidOutcome */
    public function validate(MatchOutcome $outcome): void;

    public function allowsDraw(): bool;

    public function type(): string;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
