<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage;

use InvalidArgumentException;

final readonly class StageSettings
{
    public function __construct(
        /** Матч за третье место в олимпийской сетке. */
        public bool $thirdPlaceMatch = false,
        /** Количество кругов в круговой системе: 1 — каждый с каждым по разу, 2 — дома и в гостях. */
        public int $legs = 1,
        /** Сколько туров играет швейцарская система. Ноль означает «посчитать по числу участников». */
        public int $rounds = 0,
    ) {
        if ($legs < 1 || $legs > 2) {
            throw new InvalidArgumentException('Legs must be 1 or 2');
        }
        if ($rounds < 0 || $rounds > 20) {
            throw new InvalidArgumentException('Rounds must be between 0 and 20');
        }
    }

    public static function default(): self
    {
        return new self;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $legs = $data['legs'] ?? 1;

        $rounds = $data['rounds'] ?? 0;

        return new self(
            thirdPlaceMatch: (bool) ($data['third_place_match'] ?? false),
            legs: is_int($legs) ? $legs : 1,
            rounds: is_int($rounds) ? $rounds : 0,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['third_place_match' => $this->thirdPlaceMatch, 'legs' => $this->legs, 'rounds' => $this->rounds];
    }
}
