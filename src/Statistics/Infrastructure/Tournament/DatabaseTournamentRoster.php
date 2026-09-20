<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Infrastructure\Tournament;

use Illuminate\Database\ConnectionInterface;
use stdClass;
use Tournaments\Statistics\Domain\Roster\CompetitorType;
use Tournaments\Statistics\Domain\Roster\RosterEntry;
use Tournaments\Statistics\Domain\Roster\TournamentRoster;

/**
 * Мост к контексту «Турнир». Читает состав напрямую из таблиц: статистике нужны
 * только имена и типы, поднимать ради этого агрегаты незачем.
 */
final readonly class DatabaseTournamentRoster implements TournamentRoster
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function participantsOf(string $tournamentId): array
    {
        return array_values($this->db->table('participants')
            ->where('tournament_id', $tournamentId)
            ->orderBy('seed')
            ->get()
            ->map(static fn (stdClass $row) => new RosterEntry(
                participantId: $row->id,
                name: $row->name,
                type: CompetitorType::from($row->type),
            ))
            ->all());
    }

    public function ownerOf(string $tournamentId): ?int
    {
        $owner = $this->db->table('tournaments')->where('id', $tournamentId)->value('owner_id');

        return is_numeric($owner) ? (int) $owner : null;
    }

    public function link(array $links): void
    {
        foreach ($links as $participantId => $competitorId) {
            $this->db->table('participants')
                ->where('id', $participantId)
                ->update(['competitor_id' => $competitorId]);
        }
    }
}
