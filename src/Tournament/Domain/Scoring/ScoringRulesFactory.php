<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Scoring;

use InvalidArgumentException;

/** Восстановление правил и результатов из массивов. Используется репозиториями и обработчиками команд. */
final class ScoringRulesFactory
{
    /** @param array<string, mixed> $data */
    public static function rulesFromArray(array $data): ScoringRules
    {
        return match ($data['type'] ?? null) {
            SetBasedRules::TYPE => new SetBasedRules(
                setsToWin: self::int($data, 'sets_to_win'),
                pointsPerSet: self::int($data, 'points_per_set'),
                pointsInDecidingSet: self::int($data, 'points_in_deciding_set'),
                minMargin: self::int($data, 'min_margin', 2),
            ),
            PointBasedRules::TYPE => new PointBasedRules(drawAllowed: (bool) ($data['draw_allowed'] ?? true)),
            default => throw new InvalidArgumentException('Unknown scoring rules type'),
        };
    }

    /** @param array<string, mixed> $data */
    public static function outcomeFromArray(array $data): MatchOutcome
    {
        return match ($data['type'] ?? null) {
            SetBasedOutcome::TYPE => SetBasedOutcome::of(self::sets($data)),
            PointOutcome::TYPE => new PointOutcome(self::int($data, 'home'), self::int($data, 'away')),
            Walkover::TYPE => new Walkover(Side::from(self::string($data, 'winner'))),
            default => throw new InvalidArgumentException('Unknown outcome type'),
        };
    }

    /** @param array<string, mixed> $data */
    private static function int(array $data, string $key, ?int $default = null): int
    {
        $value = $data[$key] ?? $default;
        if (! is_int($value)) {
            throw new InvalidArgumentException(sprintf('"%s" must be an integer', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('"%s" must be a string', $key));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{0: int, 1: int}>
     */
    private static function sets(array $data): array
    {
        $sets = $data['sets'] ?? null;
        if (! is_array($sets)) {
            throw new InvalidArgumentException('"sets" must be an array');
        }
        $result = [];
        foreach ($sets as $set) {
            if (! is_array($set) || ! isset($set[0], $set[1]) || ! is_int($set[0]) || ! is_int($set[1])) {
                throw new InvalidArgumentException('Each set must be a pair of integers');
            }
            $result[] = [$set[0], $set[1]];
        }

        return $result;
    }
}
