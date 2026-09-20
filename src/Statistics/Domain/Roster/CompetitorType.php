<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Domain\Roster;

/** Кем выступает участник: сам за себя, в паре или в команде. Разрезы статистики совпадают с этим делением. */
enum CompetitorType: string
{
    case Player = 'player';
    case Pair = 'pair';
    case Team = 'team';
}
