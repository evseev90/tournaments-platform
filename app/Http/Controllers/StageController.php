<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Tournaments\Tournament\Application\Command\DiscardStage;
use Tournaments\Tournament\Application\Command\DiscardStageHandler;
use Tournaments\Tournament\Application\Command\GenerateGroups;
use Tournaments\Tournament\Application\Command\GenerateGroupsHandler;
use Tournaments\Tournament\Application\Command\GeneratePlayoff;
use Tournaments\Tournament\Application\Command\GeneratePlayoffHandler;
use Tournaments\Tournament\Application\Command\GenerateStage;
use Tournaments\Tournament\Application\Command\GenerateStageHandler;
use Tournaments\Tournament\Application\Command\PairNextRound;
use Tournaments\Tournament\Application\Command\PairNextRoundHandler;
use Tournaments\Tournament\Domain\Stage\StageFormat;

final class StageController extends Controller
{
    public function store(Request $request, GenerateStageHandler $handler): RedirectResponse
    {
        /** @var array{format: string, settings?: array<string, mixed>} $data */
        $data = $request->validate([
            'format' => ['required', Rule::enum(StageFormat::class)],
            'settings' => ['nullable', 'array'],
            'settings.third_place_match' => ['nullable', 'boolean'],
            'settings.legs' => ['nullable', 'integer', 'in:1,2'],
            'settings.rounds' => ['nullable', 'integer', 'between:0,20'],
        ]);
        $handler(new GenerateStage($this->organizerTournament($request)->id, $data['format'], $data['settings'] ?? []));

        return $this->backToManage($request, 'Сетка сгенерирована');
    }

    public function storeGroups(Request $request, GenerateGroupsHandler $handler): RedirectResponse
    {
        /** @var array{groups: int, legs?: int} $data */
        $data = $request->validate([
            'groups' => ['required', 'integer', 'between:2,16'],
            'legs' => ['nullable', 'integer', 'in:1,2'],
        ]);
        $handler(new GenerateGroups($this->organizerTournament($request)->id, $data['groups'], $data['legs'] ?? 1));

        return $this->backToManage($request, 'Группы сформированы');
    }

    public function storePlayoff(Request $request, GeneratePlayoffHandler $handler): RedirectResponse
    {
        /** @var array{advance_per_group: int, third_place_match?: bool} $data */
        $data = $request->validate([
            'advance_per_group' => ['required', 'integer', 'between:1,8'],
            'third_place_match' => ['nullable', 'boolean'],
        ]);
        $handler(new GeneratePlayoff(
            $this->organizerTournament($request)->id,
            $data['advance_per_group'],
            (bool) ($data['third_place_match'] ?? false),
        ));

        return $this->backToManage($request, 'Плей-офф собран по итогам групп');
    }

    public function pairRound(Request $request, string $token, string $stage, PairNextRoundHandler $handler): RedirectResponse
    {
        $handler(new PairNextRound($this->organizerTournament($request)->id, $stage));

        return $this->backToManage($request, 'Пары следующего тура составлены');
    }

    public function destroy(Request $request, DiscardStageHandler $handler): RedirectResponse
    {
        $handler(new DiscardStage($this->organizerTournament($request)->id));

        return $this->backToManage($request, 'Сетка отменена, состав снова можно менять');
    }
}
