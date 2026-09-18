<?php

declare(strict_types=1);

namespace Tournaments\Shared\Application;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
