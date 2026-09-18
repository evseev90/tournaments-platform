<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Scoring;

/** Техническая победа: неявка, снятие, отказ. Счёт не фиксируется. */
final readonly class Walkover implements MatchOutcome
{
    public const string TYPE = 'walkover';

    public function __construct(
        public Side $winnerSide,
    ) {}

    public function winner(): Side
    {
        return $this->winnerSide;
    }

    public function isDraw(): bool
    {
        return false;
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function toArray(): array
    {
        return ['type' => self::TYPE, 'winner' => $this->winnerSide->value];
    }

    public function equals(MatchOutcome $other): bool
    {
        return $other instanceof self && $other->winnerSide === $this->winnerSide;
    }
}
