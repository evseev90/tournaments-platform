<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Tournaments\Tournament\Application\Command\AddParticipants;
use Tournaments\Tournament\Application\Command\AddParticipantsHandler;
use Tournaments\Tournament\Application\Command\RemoveParticipant;
use Tournaments\Tournament\Application\Command\RemoveParticipantHandler;
use Tournaments\Tournament\Application\Command\ReorderParticipants;
use Tournaments\Tournament\Application\Command\ReorderParticipantsHandler;
use Tournaments\Tournament\Application\Command\ShuffleParticipants;
use Tournaments\Tournament\Application\Command\ShuffleParticipantsHandler;
use Tournaments\Tournament\Application\Command\UpdateParticipant;
use Tournaments\Tournament\Application\Command\UpdateParticipantHandler;
use Tournaments\Tournament\Domain\Tournament\ParticipantType;

final class ParticipantController extends Controller
{
    public function store(Request $request, AddParticipantsHandler $handler): RedirectResponse
    {
        /** @var array{type: string, participants: array<int, array{name: string, members?: array<int, string>}>} $data */
        $data = $request->validate([
            'type' => ['required', Rule::enum(ParticipantType::class)],
            'participants' => ['required', 'array', 'min:1', 'max:128'],
            'participants.*.name' => ['required', 'string', 'max:100'],
            'participants.*.members' => ['nullable', 'array', 'max:20'],
            'participants.*.members.*' => ['string', 'max:100'],
        ]);
        $participants = array_values(array_map(static fn (array $p) => ['name' => $p['name'], 'members' => array_values($p['members'] ?? [])], $data['participants']));
        $handler(new AddParticipants($this->organizerTournament($request)->id, $data['type'], $participants));

        return $this->backToManage($request);
    }

    public function update(Request $request, string $token, string $participant, UpdateParticipantHandler $handler): RedirectResponse
    {
        /** @var array{name: string, members?: array<int, string>} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'members' => ['nullable', 'array', 'max:20'],
            'members.*' => ['string', 'max:100'],
        ]);
        $handler(new UpdateParticipant($this->organizerTournament($request)->id, $participant, $data['name'], array_values($data['members'] ?? [])));

        return $this->backToManage($request);
    }

    public function destroy(Request $request, string $token, string $participant, RemoveParticipantHandler $handler): RedirectResponse
    {
        $handler(new RemoveParticipant($this->organizerTournament($request)->id, $participant));

        return $this->backToManage($request);
    }

    public function reorder(Request $request, ReorderParticipantsHandler $handler): RedirectResponse
    {
        /** @var array{order: array<int, string>} $data */
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['uuid']]);
        $handler(new ReorderParticipants($this->organizerTournament($request)->id, array_values($data['order'])));

        return $this->backToManage($request);
    }

    public function shuffle(Request $request, ShuffleParticipantsHandler $handler): RedirectResponse
    {
        $handler(new ShuffleParticipants($this->organizerTournament($request)->id));

        return $this->backToManage($request, 'Жеребьёвка проведена');
    }
}
