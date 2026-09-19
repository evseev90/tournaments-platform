<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Domain\Planning;

use Tournaments\Scheduling\Domain\TournamentRef;

/**
 * Порт в контекст «Турнир». Реализация лежит в инфраструктуре и переводит матчи стадий
 * на язык расписания: проходы без игры сюда не попадают, их не на чем играть.
 */
interface PlannableMatches
{
    /** @return list<PlannableMatch> */
    public function of(TournamentRef $tournament): array;
}
