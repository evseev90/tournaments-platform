<?php

declare(strict_types=1);

namespace Tournaments\Shared\Infrastructure\Laravel;

use DateTimeImmutable;
use DateTimeZone;
use Tournaments\Shared\Application\Clock;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
