<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

/** Групповой этап: участники разводятся змейкой по группам, в каждой круговая. */
final readonly class GenerateGroups
{
    public function __construct(
        public string $tournamentId,
        public int $groups,
        public int $legs = 1,
    ) {}
}
