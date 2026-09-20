<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Tournament;

use InvalidArgumentException;
use Stringable;

/**
 * Владелец турнира. Аутентификация живёт в приложении, домену важен только факт владения,
 * поэтому здесь просто идентификатор.
 */
final readonly class OwnerId implements Stringable
{
    public function __construct(
        public int $value,
    ) {
        if ($value < 1) {
            throw new InvalidArgumentException('Owner id must be positive');
        }
    }

    public function equals(?self $other): bool
    {
        return $other !== null && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
