<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage;

enum FixtureStatus: string
{
    /** Ждёт участников из предыдущих матчей. */
    case Pending = 'pending';
    /** Оба участника известны, можно играть. */
    case Ready = 'ready';
    case Finished = 'finished';
    /** Проход без игры: сопернику не хватило пары в сетке. */
    case Bye = 'bye';
}
