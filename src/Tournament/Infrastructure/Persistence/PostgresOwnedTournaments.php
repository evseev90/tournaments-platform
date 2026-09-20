<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;
use Tournaments\Tournament\Application\Query\OwnedTournaments;
use Tournaments\Tournament\Application\Query\TournamentSummary;

/** Модель чтения для списка турниров: одна выборка со счётчиками вместо загрузки агрегатов. */
final readonly class PostgresOwnedTournaments implements OwnedTournaments
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function of(int $ownerId): array
    {
        $rows = $this->db->table('tournaments')
            ->where('owner_id', $ownerId)
            ->orderByDesc('created_at')
            ->selectRaw('tournaments.*')
            ->selectSub(
                $this->db->table('participants')->selectRaw('count(*)')->whereColumn('participants.tournament_id', 'tournaments.id'),
                'participants_count',
            )
            ->selectSub(
                $this->db->table('fixtures')
                    ->join('stages', 'stages.id', '=', 'fixtures.stage_id')
                    ->selectRaw('count(*)')
                    ->whereColumn('stages.tournament_id', 'tournaments.id')
                    ->whereNull('fixtures.outcome')
                    ->where('fixtures.bye', false),
                'matches_left',
            )
            ->selectSub(
                $this->db->table('fixtures')
                    ->join('stages', 'stages.id', '=', 'fixtures.stage_id')
                    ->selectRaw('count(*)')
                    ->whereColumn('stages.tournament_id', 'tournaments.id')
                    ->where('fixtures.bye', false),
                'matches_total',
            )
            ->get();

        return array_values($rows->map(static fn (stdClass $row) => new TournamentSummary(
            id: $row->id,
            name: $row->name,
            sport: $row->sport,
            status: $row->status,
            adminToken: $row->admin_token,
            publicSlug: $row->public_slug,
            createdAt: (new DateTimeImmutable($row->created_at))->format(DateTimeInterface::ATOM),
            participants: (int) $row->participants_count,
            matchesPlayed: (int) $row->matches_total - (int) $row->matches_left,
            matchesTotal: (int) $row->matches_total,
        ))->all());
    }
}
