<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Scoring;

use Tournaments\Tournament\Domain\Exception\InvalidOutcome;

/** Правила для матчей с одним числом на сторону. Задел под шахматы и игровые виды. */
final readonly class PointBasedRules implements ScoringRules
{
    public const string TYPE = 'points';

    public function __construct(
        public bool $drawAllowed = true,
    ) {}

    public function validate(MatchOutcome $outcome): void
    {
        if ($outcome instanceof Walkover) {
            return;
        }
        if (! $outcome instanceof PointOutcome) {
            throw new InvalidOutcome(sprintf('Expected point-based outcome, got "%s"', $outcome->type()));
        }
        if ($outcome->isDraw() && ! $this->drawAllowed) {
            throw new InvalidOutcome('Match cannot end in a draw');
        }
    }

    public function allowsDraw(): bool
    {
        return $this->drawAllowed;
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function toArray(): array
    {
        return ['type' => self::TYPE, 'draw_allowed' => $this->drawAllowed];
    }
}
