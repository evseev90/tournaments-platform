<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tournaments\Tournament\Application\Query\TournamentFinder;

/**
 * Ссылка организатора: /manage/{token}. Токен — единственный ключ доступа,
 * поэтому неверный токен неотличим от несуществующего турнира: 404.
 */
final readonly class ResolveOrganizerTournament
{
    public const string ATTRIBUTE = 'organizer_tournament';

    public function __construct(
        private TournamentFinder $finder,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');
        $tournament = is_string($token) ? $this->finder->byAdminToken($token) : null;
        abort_if($tournament === null, 404);

        $request->attributes->set(self::ATTRIBUTE, $tournament);

        return $next($request);
    }
}
