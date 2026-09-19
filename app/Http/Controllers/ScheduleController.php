<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tournaments\Scheduling\Application\Command\AddVenue;
use Tournaments\Scheduling\Application\Command\AddVenueHandler;
use Tournaments\Scheduling\Application\Command\ChangeScheduleSettings;
use Tournaments\Scheduling\Application\Command\ChangeScheduleSettingsHandler;
use Tournaments\Scheduling\Application\Command\ClearSchedule;
use Tournaments\Scheduling\Application\Command\ClearScheduleHandler;
use Tournaments\Scheduling\Application\Command\MoveMatch;
use Tournaments\Scheduling\Application\Command\MoveMatchHandler;
use Tournaments\Scheduling\Application\Command\PlanSchedule;
use Tournaments\Scheduling\Application\Command\PlanScheduleHandler;
use Tournaments\Scheduling\Application\Command\RemoveVenue;
use Tournaments\Scheduling\Application\Command\RemoveVenueHandler;
use Tournaments\Scheduling\Application\Command\RenameVenue;
use Tournaments\Scheduling\Application\Command\RenameVenueHandler;

final class ScheduleController extends Controller
{
    public function storeVenue(Request $request, AddVenueHandler $handler): RedirectResponse
    {
        /** @var array{name: string} $data */
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        $handler(new AddVenue($this->organizerTournament($request)->id, $data['name']));

        return $this->backToManage($request);
    }

    public function updateVenue(Request $request, string $token, string $venue, RenameVenueHandler $handler): RedirectResponse
    {
        /** @var array{name: string} $data */
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        $handler(new RenameVenue($this->organizerTournament($request)->id, $venue, $data['name']));

        return $this->backToManage($request);
    }

    public function destroyVenue(Request $request, string $token, string $venue, RemoveVenueHandler $handler): RedirectResponse
    {
        $handler(new RemoveVenue($this->organizerTournament($request)->id, $venue));

        return $this->backToManage($request);
    }

    public function settings(Request $request, ChangeScheduleSettingsHandler $handler): RedirectResponse
    {
        /** @var array{settings: array<string, mixed>} $data */
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.starts_at' => ['required', 'date'],
            'settings.match_minutes' => ['required', 'integer', 'between:1,600'],
            'settings.break_minutes' => ['required', 'integer', 'between:0,240'],
            'settings.rest_minutes' => ['required', 'integer', 'between:0,600'],
        ]);
        $handler(new ChangeScheduleSettings($this->organizerTournament($request)->id, $data['settings']));

        return $this->backToManage($request, 'Параметры расписания сохранены');
    }

    public function plan(Request $request, PlanScheduleHandler $handler): RedirectResponse
    {
        $handler(new PlanSchedule($this->organizerTournament($request)->id));

        return $this->backToManage($request, 'Расписание составлено');
    }

    public function move(Request $request, string $token, string $match, MoveMatchHandler $handler): RedirectResponse
    {
        /** @var array{venue_id: string, starts_at: string} $data */
        $data = $request->validate([
            'venue_id' => ['required', 'uuid'],
            'starts_at' => ['required', 'date'],
        ]);
        $handler(new MoveMatch($this->organizerTournament($request)->id, $match, $data['venue_id'], $data['starts_at']));

        return $this->backToManage($request, 'Матч перенесён');
    }

    public function clear(Request $request, ClearScheduleHandler $handler): RedirectResponse
    {
        $handler(new ClearSchedule($this->organizerTournament($request)->id));

        return $this->backToManage($request, 'Расписание очищено');
    }
}
