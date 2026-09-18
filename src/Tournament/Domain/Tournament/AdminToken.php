<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Tournament;

use InvalidArgumentException;

/** Секрет организатора. Знаешь токен — управляешь турниром. */
final readonly class AdminToken
{
    private function __construct(
        public string $value,
    ) {}

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(20)));
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/^[0-9a-f]{40}$/', $value) !== 1) {
            throw new InvalidArgumentException('Malformed admin token');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
