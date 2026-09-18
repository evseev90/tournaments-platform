<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Infrastructure\Persistence;

use InvalidArgumentException;

/** Разбор jsonb-колонок в типы, которые ждёт домен. */
final class Json
{
    /** @return array<string, mixed> */
    public static function map(mixed $value): array
    {
        $decoded = self::decode($value);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Expected a JSON object');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /** @return list<string> */
    public static function list(mixed $value): array
    {
        $decoded = self::decode($value);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Expected a JSON array');
        }

        return array_values(array_map(static fn (mixed $item): string => is_string($item) ? $item : throw new InvalidArgumentException('Expected strings'), $decoded));
    }

    /** @return array<string, mixed>|null */
    public static function nullableMap(mixed $value): ?array
    {
        return $value === null ? null : self::map($value);
    }

    private static function decode(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException('Expected JSON string');
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }
}
