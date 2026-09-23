<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Query;

/**
 * Турниры, которые идут прямо сейчас, для витрины на главной. Список публичный:
 * наружу из сводки отдаются только поля для зрителей, токен организатора — никогда.
 */
interface RunningTournaments
{
    /**
     * Сначала те, где счёт вводили последним.
     *
     * @return list<TournamentSummary>
     */
    public function latest(int $limit): array;
}
