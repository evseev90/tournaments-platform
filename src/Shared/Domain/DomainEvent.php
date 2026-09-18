<?php

declare(strict_types=1);

namespace Tournaments\Shared\Domain;

use DateTimeImmutable;

interface DomainEvent
{
    public function aggregateId(): string;

    public function occurredAt(): DateTimeImmutable;
}
