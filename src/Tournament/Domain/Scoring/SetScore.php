<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Scoring;

use Tournaments\Tournament\Domain\Exception\InvalidOutcome;

final readonly class SetScore
{
    public function __construct(
        public int $home,
        public int $away,
    ) {
        if ($home < 0 || $away < 0) {
            throw new InvalidOutcome('Set score cannot be negative');
        }
        if ($home === $away) {
            throw new InvalidOutcome(sprintf('Set cannot end in a tie: %d:%d', $home, $away));
        }
    }

    public function winner(): Side
    {
        return $this->home > $this->away ? Side::Home : Side::Away;
    }

    public function pointsOf(Side $side): int
    {
        return $side === Side::Home ? $this->home : $this->away;
    }

    public function margin(): int
    {
        return abs($this->home - $this->away);
    }

    public function equals(self $other): bool
    {
        return $this->home === $other->home && $this->away === $other->away;
    }
}
