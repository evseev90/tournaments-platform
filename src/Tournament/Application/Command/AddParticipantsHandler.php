<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Tournament\Domain\Tournament\Name;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\ParticipantType;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final readonly class AddParticipantsHandler
{
    public function __construct(
        private TournamentRepository $tournaments,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /** @return list<string> идентификаторы добавленных участников в порядке команды */
    public function __invoke(AddParticipants $command): array
    {
        $tournament = $this->tournaments->get(TournamentId::fromString($command->tournamentId));
        $type = ParticipantType::from($command->type);
        $now = $this->clock->now();

        // Сначала проверяем весь список, чтобы одна пустая строка не добавила половину участников.
        $names = array_map(static fn (array $data) => new Name($data['name']), $command->participants);

        $ids = [];
        foreach ($command->participants as $index => $data) {
            $id = ParticipantId::generate();
            $tournament->addParticipant($id, $names[$index], $type, $data['members'] ?? [], $now);
            $ids[] = $id->value;
        }

        $this->tournaments->save($tournament);
        $this->events->dispatch(...$tournament->releaseEvents());

        return $ids;
    }
}
