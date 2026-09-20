<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Tournaments\Statistics\Application\Query\CompetitorStats;
use Tournaments\Statistics\Application\Query\Statistics;
use Tournaments\Statistics\Domain\Roster\CompetitorType;

final class StatisticsController extends Controller
{
    public function index(Request $request, Statistics $statistics): Response
    {
        $owner = $this->currentOwnerId($request);
        abort_if($owner === null, 403);

        $type = $request->query('type');
        $type = is_string($type) && CompetitorType::tryFrom($type) !== null ? $type : null;

        return Inertia::render('Players/Index', [
            'type' => $type,
            'competitors' => array_map(
                static fn (CompetitorStats $stats) => [...(array) $stats, 'winRate' => $stats->winRate()],
                $statistics->leaderboard($owner, $type),
            ),
        ]);
    }

    public function show(Request $request, string $competitor, Statistics $statistics): Response
    {
        $owner = $this->currentOwnerId($request);
        abort_if($owner === null, 403);

        $profile = $statistics->profile($owner, $competitor);
        abort_if($profile === null, 404);

        return Inertia::render('Players/Show', [
            'stats' => [...(array) $profile->stats, 'winRate' => $profile->stats->winRate()],
            'matches' => $profile->recentMatches,
            'rivals' => $profile->rivals,
        ]);
    }
}
