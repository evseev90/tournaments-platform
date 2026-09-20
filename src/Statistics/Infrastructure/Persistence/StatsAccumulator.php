<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Infrastructure\Persistence;

use stdClass;
use Tournaments\Statistics\Application\Query\CompetitorStats;
use Tournaments\Tournament\Domain\Scoring\ScoringRulesFactory;
use Tournaments\Tournament\Domain\Scoring\SetBasedOutcome;
use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Infrastructure\Persistence\Json;

/**
 * Копилка показателей одного участника.
 *
 * @internal
 */
final class StatsAccumulator
{
    private int $matches = 0;

    private int $wins = 0;

    private int $draws = 0;

    private int $losses = 0;

    private int $setsWon = 0;

    private int $setsLost = 0;

    private int $pointsWon = 0;

    private int $pointsLost = 0;

    /** @var array<string, true> */
    private array $tournaments = [];

    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $type,
    ) {}

    public function add(stdClass $row, Side $side): void
    {
        $outcome = ScoringRulesFactory::outcomeFromArray(Json::map($row->outcome));

        $this->matches++;
        $this->tournaments[$row->tournament_id] = true;

        match ($outcome->winner()) {
            $side => $this->wins++,
            null => $this->draws++,
            default => $this->losses++,
        };

        if ($outcome instanceof SetBasedOutcome) {
            $this->setsWon += $outcome->setsWon($side);
            $this->setsLost += $outcome->setsWon($side->opposite());
            $this->pointsWon += $outcome->pointsWon($side);
            $this->pointsLost += $outcome->pointsWon($side->opposite());
        }
    }

    public function toStats(): CompetitorStats
    {
        return new CompetitorStats(
            id: $this->id,
            name: $this->name,
            type: $this->type,
            tournaments: count($this->tournaments),
            matches: $this->matches,
            wins: $this->wins,
            draws: $this->draws,
            losses: $this->losses,
            setsWon: $this->setsWon,
            setsLost: $this->setsLost,
            pointsWon: $this->pointsWon,
            pointsLost: $this->pointsLost,
        );
    }
}
