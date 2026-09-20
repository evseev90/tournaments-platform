<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Application\Query;

interface Statistics
{
    /**
     * Сводка по всем участникам аккаунта.
     *
     * @return list<CompetitorStats>
     */
    public function leaderboard(int $ownerId, ?string $type = null): array;

    public function profile(int $ownerId, string $competitorId): ?CompetitorProfile;
}
