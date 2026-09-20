<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SportPresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Tournaments\Tournament\Application\Command\ClaimTournament;
use Tournaments\Tournament\Application\Command\ClaimTournamentHandler;
use Tournaments\Tournament\Application\Query\OwnedTournaments;
use Tournaments\Tournament\Application\Query\TournamentSummary;

final class MyTournamentsController extends Controller
{
    public function index(Request $request, OwnedTournaments $tournaments): Response
    {
        $owner = $this->currentOwnerId($request);
        abort_if($owner === null, 403);

        return Inertia::render('My/Index', [
            'tournaments' => array_map(
                static fn (TournamentSummary $summary) => [
                    ...(array) $summary,
                    'manageUrl' => route('manage.show', ['token' => $summary->adminToken]),
                    'publicUrl' => route('public.show', ['slug' => $summary->publicSlug]),
                    'sportLabel' => SportPresets::labelOf($summary->sport),
                ],
                $tournaments->of($owner),
            ),
        ]);
    }

    /** Забирает турнир, созданный до регистрации: ссылка организатора уже на руках. */
    public function claim(Request $request, ClaimTournamentHandler $handler): RedirectResponse
    {
        $owner = $this->currentOwnerId($request);
        abort_if($owner === null, 403);

        $handler(new ClaimTournament($this->organizerTournament($request)->id, $owner));

        return $this->backToManage($request, 'Турнир сохранён в вашем аккаунте');
    }
}
