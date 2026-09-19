<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Domain;

use Tournaments\Scheduling\Domain\Exception\InvalidVenueName;

/** Площадка: стол, корт, поле. Одновременно на ней идёт один матч. */
final class Venue
{
    public const int MAX_NAME_LENGTH = 60;

    public private(set) string $name;

    public function __construct(
        public readonly VenueId $id,
        string $name,
        public private(set) int $order,
    ) {
        $this->name = self::normalizeName($name);
    }

    public function rename(string $name): void
    {
        $this->name = self::normalizeName($name);
    }

    public function reorder(int $order): void
    {
        $this->order = $order;
    }

    private static function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            throw new InvalidVenueName('Venue name cannot be empty');
        }
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new InvalidVenueName(sprintf('Venue name cannot be longer than %d characters', self::MAX_NAME_LENGTH));
        }

        return $name;
    }
}
