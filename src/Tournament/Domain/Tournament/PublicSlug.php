<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Tournament;

use InvalidArgumentException;

/** Короткий публичный идентификатор для ссылки зрителей. */
final readonly class PublicSlug
{
    private const string ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    private const int LENGTH = 10;

    private function __construct(
        public string $value,
    ) {}

    public static function generate(): self
    {
        $slug = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $slug .= self::ALPHABET[random_int(0, $max)];
        }

        return new self($slug);
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/^[a-z0-9]{6,20}$/', $value) !== 1) {
            throw new InvalidArgumentException('Malformed public slug');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
