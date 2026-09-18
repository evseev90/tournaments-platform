<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;
use Tournaments\Shared\Application\Clock;

final class FrozenClock implements Clock
{
    public function __construct(
        public DateTimeImmutable $now = new DateTimeImmutable('2026-09-18 12:00:00'),
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
