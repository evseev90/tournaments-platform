<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Tournaments\Scheduling\Application\Query\ScheduleFinder;
use Tournaments\Tournament\Application\Query\TournamentFinder;

final class PublicController extends Controller
{
    public function show(string $slug, TournamentFinder $finder, ScheduleFinder $schedules): Response
    {
        $tournament = $finder->bySlug($slug);
        abort_if($tournament === null, 404);

        return Inertia::render('Public/Show', [
            'tournament' => $tournament,
            'schedule' => $schedules->ofTournament($tournament->id),
            'urls' => ['public' => route('public.show', ['slug' => $slug])],
        ]);
    }
}
