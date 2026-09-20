<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Query;

/**
 * Список турниров аккаунта. Здесь чтение идёт мимо агрегатов: на странице нужны
 * только заголовки и счётчики, а турниров может быть много.
 */
interface OwnedTournaments
{
    /** @return list<TournamentSummary> */
    public function of(int $ownerId): array;
}
