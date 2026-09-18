<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tournaments\Tournament\Application\Command\RecordFixtureResult;
use Tournaments\Tournament\Application\Command\RecordFixtureResultHandler;
use Tournaments\Tournament\Application\Command\RevertFixtureResult;
use Tournaments\Tournament\Application\Command\RevertFixtureResultHandler;

final class ResultController extends Controller
{
    public function store(Request $request, string $token, string $stage, string $fixture, RecordFixtureResultHandler $handler): RedirectResponse
    {
        /** @var array{outcome: array<string, mixed>} $data */
        $data = $request->validate([
            'outcome' => ['required', 'array'],
            'outcome.type' => ['required', 'in:sets,points,walkover'],
            'outcome.sets' => ['required_if:outcome.type,sets', 'array', 'min:1', 'max:9'],
            'outcome.sets.*' => ['array', 'size:2'],
            'outcome.sets.*.*' => ['integer', 'between:0,999'],
            'outcome.home' => ['required_if:outcome.type,points', 'integer', 'between:0,999'],
            'outcome.away' => ['required_if:outcome.type,points', 'integer', 'between:0,999'],
            'outcome.winner' => ['required_if:outcome.type,walkover', 'in:home,away'],
        ]);
        $handler(new RecordFixtureResult($this->organizerTournament($request)->id, $stage, $fixture, $data['outcome']));

        return $this->backToManage($request);
    }

    public function destroy(Request $request, string $token, string $stage, string $fixture, RevertFixtureResultHandler $handler): RedirectResponse
    {
        $handler(new RevertFixtureResult($stage, $fixture));

        return $this->backToManage($request, 'Результат отменён');
    }
}
