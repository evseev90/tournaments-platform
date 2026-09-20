<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Application\Query;

final readonly class CompetitorStats
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public int $tournaments,
        public int $matches,
        public int $wins,
        public int $draws,
        public int $losses,
        public int $setsWon,
        public int $setsLost,
        public int $pointsWon,
        public int $pointsLost,
    ) {}

    public function winRate(): float
    {
        return $this->matches === 0 ? 0.0 : round($this->wins / $this->matches * 100, 1);
    }
}
