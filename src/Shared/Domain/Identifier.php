<?php

declare(strict_types=1);

namespace Tournaments\Shared\Domain;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Stringable;

abstract class Identifier implements Stringable
{
    final private function __construct(
        public readonly string $value,
    ) {}

    public static function generate(): static
    {
        return new static(Uuid::uuid7()->toString());
    }

    public static function fromString(string $value): static
    {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid %s', $value, static::class));
        }

        return new static($value);
    }

    public function equals(?Identifier $other): bool
    {
        return $other !== null && $other::class === static::class && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
