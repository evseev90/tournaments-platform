<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;
use Tournaments\Statistics\Application\Query\CompetitorProfile;
use Tournaments\Statistics\Application\Query\CompetitorStats;
use Tournaments\Statistics\Application\Query\MatchRecord;
use Tournaments\Statistics\Application\Query\Statistics;
use Tournaments\Tournament\Domain\Scoring\ScoringRulesFactory;
use Tournaments\Tournament\Domain\Scoring\SetBasedOutcome;
use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Infrastructure\Persistence\Json;

/**
 * Статистика считается по сыгранным матчам на лету: у клуба их тысячи, а не миллионы,
 * поэтому отдельная проекция только добавила бы способов рассинхронизироваться.
 *
 * Разбор счёта отдан доменным объектам: правила «кто победил» и «сколько партий» живут там,
 * и дублировать их в SQL нельзя.
 */
final readonly class PostgresStatistics implements Statistics
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function leaderboard(int $ownerId, ?string $type = null): array
    {
        // Начинаем с полного справочника: участник без сыгранных матчей тоже должен быть виден.
        $accumulators = [];
        foreach ($this->competitorsOf($ownerId, $type) as $competitor) {
            $accumulators[$competitor->id] = new StatsAccumulator($competitor->id, $competitor->name, $competitor->type);
        }

        foreach ($this->rows($ownerId) as $row) {
            foreach ($this->sides($row) as [$competitorId, , , $side]) {
                // Участник мог быть отфильтрован по типу: тогда его матчи нас не интересуют.
                if (isset($accumulators[$competitorId])) {
                    $accumulators[$competitorId]->add($row, $side);
                }
            }
        }

        $stats = array_map(static fn (StatsAccumulator $acc) => $acc->toStats(), array_values($accumulators));
        usort($stats, static fn (CompetitorStats $a, CompetitorStats $b) => [$b->wins, $b->winRate(), $a->name] <=> [$a->wins, $a->winRate(), $b->name]);

        return $stats;
    }

    public function profile(int $ownerId, string $competitorId): ?CompetitorProfile
    {
        $competitor = $this->db->table('competitors')
            ->where('id', $competitorId)
            ->where('owner_id', $ownerId)
            ->first();
        if (! $competitor instanceof stdClass) {
            return null;
        }

        $accumulator = new StatsAccumulator($competitorId, $competitor->name, $competitor->type);
        $records = [];
        $rivals = [];

        foreach ($this->rows($ownerId) as $row) {
            $side = match (true) {
                $row->home_competitor === $competitorId => Side::Home,
                $row->away_competitor === $competitorId => Side::Away,
                default => null,
            };
            if ($side === null) {
                continue;
            }

            $accumulator->add($row, $side);

            $outcome = ScoringRulesFactory::outcomeFromArray(Json::map($row->outcome));
            $winner = $outcome->winner();
            $opponent = $side === Side::Home ? $row->away_name : $row->home_name;

            $records[] = new MatchRecord(
                tournamentName: $row->tournament_name,
                tournamentSlug: $row->public_slug,
                stageName: $row->stage_name ?? '',
                opponent: $opponent,
                result: $winner === null ? 'draw' : ($winner === $side ? 'win' : 'loss'),
                score: $this->scoreOf($outcome, $side),
                playedAt: $row->finished_at === null ? '' : (new DateTimeImmutable($row->finished_at))->format(DateTimeInterface::ATOM),
            );

            if ($opponent !== null && $winner !== null) {
                $rivals[$opponent] ??= ['name' => $opponent, 'wins' => 0, 'losses' => 0];
                $winner === $side ? $rivals[$opponent]['wins']++ : $rivals[$opponent]['losses']++;
            }
        }

        usort($records, static fn (MatchRecord $a, MatchRecord $b) => $b->playedAt <=> $a->playedAt);
        usort($rivals, static fn (array $a, array $b) => ($b['wins'] + $b['losses']) <=> ($a['wins'] + $a['losses']));

        return new CompetitorProfile(
            stats: $accumulator->toStats(),
            recentMatches: array_slice($records, 0, 20),
            rivals: array_slice($rivals, 0, 8),
        );
    }

    /**
     * Справочник аккаунта.
     *
     * @return list<stdClass>
     */
    private function competitorsOf(int $ownerId, ?string $type): array
    {
        return array_values($this->db->table('competitors')
            ->where('owner_id', $ownerId)
            ->when($type !== null, static fn ($query) => $query->where('type', $type))
            ->orderBy('name')
            ->get()
            ->all());
    }

    /**
     * Все сыгранные матчи аккаунта, где обе стороны опознаны.
     *
     * @return list<stdClass>
     */
    private function rows(int $ownerId): array
    {
        return array_values($this->db->table('fixtures')
            ->join('stages', 'stages.id', '=', 'fixtures.stage_id')
            ->join('tournaments', 'tournaments.id', '=', 'stages.tournament_id')
            ->join('participants as ph', 'ph.id', '=', 'fixtures.home_id')
            ->join('participants as pa', 'pa.id', '=', 'fixtures.away_id')
            ->join('competitors as ch', 'ch.id', '=', 'ph.competitor_id')
            ->join('competitors as ca', 'ca.id', '=', 'pa.competitor_id')
            ->where('tournaments.owner_id', $ownerId)
            ->whereNotNull('fixtures.outcome')
            ->select([
                'fixtures.outcome',
                'fixtures.finished_at',
                'tournaments.id as tournament_id',
                'tournaments.name as tournament_name',
                'tournaments.public_slug',
                'stages.name as stage_name',
                'ch.id as home_competitor',
                'ch.name as home_name',
                'ch.type as home_type',
                'ca.id as away_competitor',
                'ca.name as away_name',
                'ca.type as away_type',
            ])
            ->get()
            ->all());
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: Side}> */
    private function sides(stdClass $row): array
    {
        return [
            [$row->home_competitor, $row->home_name, $row->home_type, Side::Home],
            [$row->away_competitor, $row->away_name, $row->away_type, Side::Away],
        ];
    }

    private function scoreOf(object $outcome, Side $side): string
    {
        if ($outcome instanceof SetBasedOutcome) {
            return sprintf('%d:%d', $outcome->setsWon($side), $outcome->setsWon($side->opposite()));
        }

        return method_exists($outcome, 'toArray') ? (string) json_encode($outcome->toArray()) : '';
    }
}
