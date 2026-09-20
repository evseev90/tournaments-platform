<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Query;

final readonly class StandingView
{
    public function __construct(
        public string $participantId,
        public int $rank,
        public int $played,
        public int $wins,
        public int $draws,
        public int $losses,
        public int $setsWon,
        public int $setsLost,
        public int $pointsWon,
        public int $pointsLost,
        public int $buchholz,
        public int $score,
    ) {}
}
