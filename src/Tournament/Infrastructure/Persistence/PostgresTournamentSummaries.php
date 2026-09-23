<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use stdClass;
use Tournaments\Tournament\Application\Query\OwnedTournaments;
use Tournaments\Tournament\Application\Query\RunningTournaments;
use Tournaments\Tournament\Application\Query\TournamentSummary;
use Tournaments\Tournament\Domain\Tournament\TournamentStatus;

/** Модель чтения для списков турниров: одна выборка со счётчиками вместо загрузки агрегатов. */
final readonly class PostgresTournamentSummaries implements OwnedTournaments, RunningTournaments
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function of(int $ownerId): array
    {
        return $this->map($this->summaries()->where('owner_id', $ownerId)->orderByDesc('created_at'));
    }

    public function latest(int $limit): array
    {
        return $this->map($this->summaries()
            ->where('status', TournamentStatus::InProgress->value)
            ->selectSub(
                $this->db->table('fixtures')
                    ->join('stages', 'stages.id', '=', 'fixtures.stage_id')
                    ->selectRaw('coalesce(max(fixtures.finished_at), tournaments.created_at)')
                    ->whereColumn('stages.tournament_id', 'tournaments.id'),
                'last_activity_at',
            )
            ->orderByDesc('last_activity_at')
            ->limit($limit));
    }

    private function summaries(): Builder
    {
        return $this->db->table('tournaments')
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
            );
    }

    /** @return list<TournamentSummary> */
    private function map(Builder $query): array
    {
        return array_values($query->get()->map(static fn (stdClass $row) => new TournamentSummary(
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
