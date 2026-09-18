<?php

declare(strict_types=1);

namespace Tests\Support;

use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Domain\DomainEvent;

final class CollectingEventDispatcher implements EventDispatcher
{
    /** @var list<DomainEvent> */
    public array $events = [];

    public function dispatch(DomainEvent ...$events): void
    {
        $this->events = [...$this->events, ...array_values($events)];
    }

    /**
     * @param  class-string<DomainEvent>  $class
     * @return list<DomainEvent>
     */
    public function of(string $class): array
    {
        return array_values(array_filter($this->events, static fn (DomainEvent $e) => $e instanceof $class));
    }
}
