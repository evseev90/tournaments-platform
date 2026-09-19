<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SportPresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Tournaments\Scheduling\Application\Query\ScheduleFinder;
use Tournaments\Tournament\Application\Command\ChangeScoringRules;
use Tournaments\Tournament\Application\Command\ChangeScoringRulesHandler;
use Tournaments\Tournament\Application\Command\FinishTournament;
use Tournaments\Tournament\Application\Command\FinishTournamentHandler;
use Tournaments\Tournament\Application\Command\RenameTournament;
use Tournaments\Tournament\Application\Command\RenameTournamentHandler;

final class ManageController extends Controller
{
    public function show(Request $request, ScheduleFinder $schedules): Response
    {
        $tournament = $this->organizerTournament($request);
        $token = $request->route('token');

        return Inertia::render('Manage/Show', [
            'tournament' => $tournament,
            'schedule' => $schedules->ofTournament($tournament->id),
            'sports' => SportPresets::all(),
            'urls' => [
                'manage' => route('manage.show', ['token' => $token]),
                'public' => route('public.show', ['slug' => $tournament->publicSlug]),
            ],
        ]);
    }

    public function rename(Request $request, RenameTournamentHandler $handler): RedirectResponse
    {
        /** @var array{name: string} $data */
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $handler(new RenameTournament($this->organizerTournament($request)->id, $data['name']));

        return $this->backToManage($request);
    }

    public function rules(Request $request, ChangeScoringRulesHandler $handler): RedirectResponse
    {
        /** @var array{rules: array<string, mixed>} $data */
        $data = $request->validate([
            'rules' => ['required', 'array'],
            'rules.type' => ['required', 'in:sets'],
            'rules.sets_to_win' => ['required', 'integer', 'between:1,5'],
            'rules.points_per_set' => ['required', 'integer', 'between:1,99'],
            'rules.points_in_deciding_set' => ['required', 'integer', 'between:1,99'],
            'rules.min_margin' => ['required', 'integer', 'between:1,5'],
        ]);
        $handler(new ChangeScoringRules($this->organizerTournament($request)->id, $data['rules']));

        return $this->backToManage($request, 'Правила сохранены');
    }

    public function finish(Request $request, FinishTournamentHandler $handler): RedirectResponse
    {
        $handler(new FinishTournament($this->organizerTournament($request)->id));

        return $this->backToManage($request, 'Турнир завершён');
    }
}
