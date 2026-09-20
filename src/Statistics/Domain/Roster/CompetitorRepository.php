<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Domain\Roster;

interface CompetitorRepository
{
    public function findByName(int $ownerId, CompetitorType $type, string $normalizedName): ?Competitor;

    public function get(CompetitorId $id): ?Competitor;

    public function save(Competitor $competitor): void;
}
