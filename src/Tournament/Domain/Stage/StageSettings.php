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
    ) {
        if ($legs < 1 || $legs > 2) {
            throw new InvalidArgumentException('Legs must be 1 or 2');
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

        return new self(
            thirdPlaceMatch: (bool) ($data['third_place_match'] ?? false),
            legs: is_int($legs) ? $legs : 1,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['third_place_match' => $this->thirdPlaceMatch, 'legs' => $this->legs];
    }
}
