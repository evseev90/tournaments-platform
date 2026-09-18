<?php

declare(strict_types=1);

namespace App\Support;

use Tournaments\Tournament\Domain\Scoring\Sport;

/** Подписи и правила по умолчанию для формы создания. */
final class SportPresets
{
    /** @return list<array{value: string, label: string, participant_type: string, rules: array<string, mixed>}> */
    public static function all(): array
    {
        return array_map(static fn (Sport $sport) => [
            'value' => $sport->value,
            'label' => self::label($sport),
            'participant_type' => match ($sport) {
                Sport::Volleyball => 'team',
                Sport::BeachVolleyball => 'pair',
                Sport::TableTennis => 'player',
            },
            'rules' => $sport->defaultRules()->toArray(),
        ], Sport::cases());
    }

    public static function label(Sport $sport): string
    {
        return match ($sport) {
            Sport::Volleyball => 'Волейбол',
            Sport::BeachVolleyball => 'Пляжный волейбол',
            Sport::TableTennis => 'Настольный теннис',
        };
    }
}
