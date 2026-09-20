<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveOrganizerTournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tournaments\Tournament\Application\Query\TournamentView;

abstract class Controller
{
    protected function organizerTournament(Request $request): TournamentView
    {
        $tournament = $request->attributes->get(ResolveOrganizerTournament::ATTRIBUTE);
        assert($tournament instanceof TournamentView);

        return $tournament;
    }

    /** Идентификатор вошедшего организатора, если он есть. */
    protected function currentOwnerId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }

    protected function backToManage(Request $request, ?string $message = null): RedirectResponse
    {
        $response = to_route('manage.show', ['token' => $request->route('token')]);

        return $message === null ? $response : $response->with('success', $message);
    }
}
