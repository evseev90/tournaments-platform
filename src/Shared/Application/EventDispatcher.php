<?php

declare(strict_types=1);

namespace Tournaments\Shared\Application;

use Tournaments\Shared\Domain\DomainEvent;

interface EventDispatcher
{
    public function dispatch(DomainEvent ...$events): void;
}
