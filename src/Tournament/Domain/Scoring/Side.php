<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Scoring;

enum Side: string
{
    case Home = 'home';
    case Away = 'away';

    public function opposite(): self
    {
        return $this === self::Home ? self::Away : self::Home;
    }
}
