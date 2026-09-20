<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use stdClass;
use Tournaments\Statistics\Domain\Roster\Competitor;
use Tournaments\Statistics\Domain\Roster\CompetitorId;
use Tournaments\Statistics\Domain\Roster\CompetitorRepository;
use Tournaments\Statistics\Domain\Roster\CompetitorType;

final readonly class PostgresCompetitorRepository implements CompetitorRepository
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function findByName(int $ownerId, CompetitorType $type, string $normalizedName): ?Competitor
    {
        $row = $this->db->table('competitors')
            ->where('owner_id', $ownerId)
            ->where('type', $type->value)
            ->where('normalized_name', $normalizedName)
            ->first();

        return $row instanceof stdClass ? $this->hydrate($row) : null;
    }

    public function get(CompetitorId $id): ?Competitor
    {
        $row = $this->db->table('competitors')->where('id', $id->value)->first();

        return $row instanceof stdClass ? $this->hydrate($row) : null;
    }

    public function save(Competitor $competitor): void
    {
        $this->db->table('competitors')->upsert([[
            'id' => $competitor->id->value,
            'owner_id' => $competitor->ownerId,
            'name' => $competitor->name,
            'normalized_name' => $competitor->normalizedName,
            'type' => $competitor->type->value,
            'created_at' => (new DateTimeImmutable)->format(DATE_ATOM),
        ]], ['id'], ['name', 'normalized_name']);
    }

    private function hydrate(stdClass $row): Competitor
    {
        return new Competitor(
            id: CompetitorId::fromString($row->id),
            ownerId: (int) $row->owner_id,
            name: $row->name,
            type: CompetitorType::from($row->type),
        );
    }
}
