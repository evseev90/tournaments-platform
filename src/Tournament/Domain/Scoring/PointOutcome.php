<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Scoring;

use Tournaments\Tournament\Domain\Exception\InvalidOutcome;

/** Результат одним числом на сторону: голы, очки, шахматная партия. Равный счёт — ничья. */
final readonly class PointOutcome implements MatchOutcome
{
    public const string TYPE = 'points';

    public function __construct(
        public int $home,
        public int $away,
    ) {
        if ($home < 0 || $away < 0) {
            throw new InvalidOutcome('Score cannot be negative');
        }
    }

    public function winner(): ?Side
    {
        return $this->home === $this->away ? null : ($this->home > $this->away ? Side::Home : Side::Away);
    }

    public function isDraw(): bool
    {
        return $this->home === $this->away;
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function toArray(): array
    {
        return ['type' => self::TYPE, 'home' => $this->home, 'away' => $this->away];
    }

    public function equals(MatchOutcome $other): bool
    {
        return $other instanceof self && $this->toArray() === $other->toArray();
    }
}
