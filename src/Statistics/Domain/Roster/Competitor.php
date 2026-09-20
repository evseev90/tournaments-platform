<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Domain\Roster;

use InvalidArgumentException;

/**
 * Запись справочника аккаунта: один и тот же игрок, пара или команда в разных турнирах.
 *
 * Участники связываются по имени: организатор вводит списки руками, отдельного выбора из справочника
 * при создании турнира нет. Поэтому имя нормализуется, а расхождения правятся переименованием.
 */
final class Competitor
{
    public private(set) string $name;

    public private(set) string $normalizedName;

    public function __construct(
        public readonly CompetitorId $id,
        public readonly int $ownerId,
        string $name,
        public readonly CompetitorType $type,
    ) {
        $this->rename($name);
    }

    public function rename(string $name): void
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('Competitor name cannot be empty');
        }

        $this->name = $name;
        $this->normalizedName = self::normalize($name);
    }

    /** Разное написание одного имени должно совпадать: регистр и буква «ё» не в счёт. */
    public static function normalize(string $name): string
    {
        $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? ''));

        return str_replace('ё', 'е', $name);
    }
}
