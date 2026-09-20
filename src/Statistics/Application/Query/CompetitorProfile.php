<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Application\Query;

final readonly class CompetitorProfile
{
    /**
     * @param  list<MatchRecord>  $recentMatches
     * @param  list<array{name: string, wins: int, losses: int}>  $rivals
     */
    public function __construct(
        public CompetitorStats $stats,
        public array $recentMatches,
        public array $rivals,
    ) {}
}
