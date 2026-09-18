<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SportPresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Tournaments\Tournament\Application\Command\CreateTournament;
use Tournaments\Tournament\Application\Command\CreateTournamentHandler;
use Tournaments\Tournament\Domain\Scoring\Sport;

final class HomeController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Home', ['sports' => SportPresets::all()]);
    }

    public function store(Request $request, CreateTournamentHandler $handler): RedirectResponse
    {
        /** @var array{name: string, sport: string, rules?: array<string, mixed>} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sport' => ['required', Rule::enum(Sport::class)],
            'rules' => ['nullable', 'array'],
            'rules.type' => ['required_with:rules', 'in:sets'],
            'rules.sets_to_win' => ['required_with:rules', 'integer', 'between:1,5'],
            'rules.points_per_set' => ['required_with:rules', 'integer', 'between:1,99'],
            'rules.points_in_deciding_set' => ['required_with:rules', 'integer', 'between:1,99'],
            'rules.min_margin' => ['required_with:rules', 'integer', 'between:1,5'],
        ]);

        $created = $handler(new CreateTournament($data['name'], $data['sport'], $data['rules'] ?? null));

        return to_route('manage.show', ['token' => $created->adminToken]);
    }
}
