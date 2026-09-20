<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage;

/** Ветка двойной сетки. У форматов с одной сеткой ветки нет. */
enum BracketSide: string
{
    case Winners = 'winners';
    case Losers = 'losers';
    case GrandFinal = 'grand_final';
}
