<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Tournament;

use Tournaments\Tournament\Domain\Exception\InvalidName;

/** Название турнира или имя участника: непустая строка без лишних пробелов. */
final readonly class Name
{
    public const int MAX_LENGTH = 100;

    public string $value;

    public function __construct(string $value)
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') {
            throw new InvalidName('Name cannot be empty');
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidName(sprintf('Name cannot be longer than %d characters', self::MAX_LENGTH));
        }
        $this->value = $value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
