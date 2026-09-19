<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Параметры раскладки. Всё в минутах: длительность матча вместе с разминкой,
 * уборка площадки между матчами и минимальный отдых участника между своими матчами.
 */
final readonly class ScheduleSettings
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public int $matchMinutes = 30,
        public int $breakMinutes = 0,
        public int $restMinutes = 10,
    ) {
        if ($matchMinutes < 1 || $matchMinutes > 600) {
            throw new InvalidArgumentException('Match duration must be between 1 and 600 minutes');
        }
        if ($breakMinutes < 0 || $breakMinutes > 240) {
            throw new InvalidArgumentException('Break must be between 0 and 240 minutes');
        }
        if ($restMinutes < 0 || $restMinutes > 600) {
            throw new InvalidArgumentException('Rest must be between 0 and 600 minutes');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $startsAt = $data['starts_at'] ?? null;

        return new self(
            startsAt: new DateTimeImmutable(is_string($startsAt) ? $startsAt : 'now'),
            matchMinutes: self::int($data, 'match_minutes', 30),
            breakMinutes: self::int($data, 'break_minutes', 0),
            restMinutes: self::int($data, 'rest_minutes', 10),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'starts_at' => $this->startsAt->format(DATE_ATOM),
            'match_minutes' => $this->matchMinutes,
            'break_minutes' => $this->breakMinutes,
            'rest_minutes' => $this->restMinutes,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function int(array $data, string $key, int $default): int
    {
        $value = $data[$key] ?? $default;

        return is_int($value) ? $value : $default;
    }
}
