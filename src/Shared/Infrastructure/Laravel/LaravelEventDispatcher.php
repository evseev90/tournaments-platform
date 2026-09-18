<?php

declare(strict_types=1);

namespace Tournaments\Shared\Infrastructure\Laravel;

use Illuminate\Contracts\Events\Dispatcher;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Domain\DomainEvent;

/** Доменные события уходят в штатный диспетчер Laravel: слушатели подписываются по классу события. */
final readonly class LaravelEventDispatcher implements EventDispatcher
{
    public function __construct(
        private Dispatcher $dispatcher,
    ) {}

    public function dispatch(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
