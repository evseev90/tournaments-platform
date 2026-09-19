<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

/** Плей-офф по итогам групп: из каждой группы проходят первые места. */
final readonly class GeneratePlayoff
{
    public function __construct(
        public string $tournamentId,
        public int $advancePerGroup = 2,
        public bool $thirdPlaceMatch = false,
    ) {}
}
