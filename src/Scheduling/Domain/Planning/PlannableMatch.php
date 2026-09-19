<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Domain\Planning;

use Tournaments\Scheduling\Domain\CompetitorId;
use Tournaments\Scheduling\Domain\MatchId;

/**
 * Матч глазами расписания. Контекст «Турнир» знает про сетки и счёт,
 * а здесь важно только: когда его можно играть, кто играет и что должно случиться раньше.
 */
final readonly class PlannableMatch
{
    /** @param list<MatchId> $dependsOn матчи, из которых в этот приходят участники */
    public function __construct(
        public MatchId $id,
        public int $order,
        public int $sequence,
        public ?CompetitorId $home,
        public ?CompetitorId $away,
        public array $dependsOn = [],
    ) {}

    public function hasBothCompetitors(): bool
    {
        return $this->home !== null && $this->away !== null;
    }
}
