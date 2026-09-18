<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Tournaments\Tournament\Application\Command\DiscardStage;
use Tournaments\Tournament\Application\Command\DiscardStageHandler;
use Tournaments\Tournament\Application\Command\GenerateStage;
use Tournaments\Tournament\Application\Command\GenerateStageHandler;
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
        ]);
        $handler(new GenerateStage($this->organizerTournament($request)->id, $data['format'], $data['settings'] ?? []));

        return $this->backToManage($request, 'Сетка сгенерирована');
    }

    public function destroy(Request $request, DiscardStageHandler $handler): RedirectResponse
    {
        $handler(new DiscardStage($this->organizerTournament($request)->id));

        return $this->backToManage($request, 'Сетка отменена, состав снова можно менять');
    }
}
