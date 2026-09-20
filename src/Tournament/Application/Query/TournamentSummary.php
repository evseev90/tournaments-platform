<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Query;

final readonly class TournamentSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public string $sport,
        public string $status,
        public string $adminToken,
        public string $publicSlug,
        public string $createdAt,
        public int $participants,
        public int $matchesPlayed,
        public int $matchesTotal,
    ) {}
}
