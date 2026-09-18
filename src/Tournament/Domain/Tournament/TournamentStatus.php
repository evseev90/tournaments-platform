<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Tournament;

enum TournamentStatus: string
{
    /** Идёт набор участников, сетка ещё не сгенерирована. */
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Finished = 'finished';
}
