<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage;

use Tournaments\Tournament\Domain\Stage\Format\FormatGenerator;
use Tournaments\Tournament\Domain\Stage\Format\RoundRobin;
use Tournaments\Tournament\Domain\Stage\Format\SingleElimination;

enum StageFormat: string
{
    case SingleElimination = 'single_elimination';
    case RoundRobin = 'round_robin';

    public function generator(): FormatGenerator
    {
        return match ($this) {
            self::SingleElimination => new SingleElimination,
            self::RoundRobin => new RoundRobin,
        };
    }

    /** Есть ли в формате выбывание: победитель идёт дальше, ничьи невозможны. */
    public function isElimination(): bool
    {
        return $this === self::SingleElimination;
    }
}
