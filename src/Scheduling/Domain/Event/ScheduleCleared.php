<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Domain\Event;

use DateTimeImmutable;
use Tournaments\Scheduling\Domain\ScheduleId;
use Tournaments\Scheduling\Domain\TournamentRef;
use Tournaments\Shared\Domain\DomainEvent;

final readonly class ScheduleCleared implements DomainEvent
{
    public function __construct(
        public TournamentRef $tournament,
        public ScheduleId $scheduleId,
        public DateTimeImmutable $occurredAt,
    ) {}

    public function aggregateId(): string
    {
        return $this->scheduleId->value;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
