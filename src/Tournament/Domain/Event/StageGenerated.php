<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Event;

use DateTimeImmutable;
use Tournaments\Shared\Domain\DomainEvent;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Tournament\TournamentId;

final readonly class StageGenerated implements DomainEvent
{
    public function __construct(
        public TournamentId $tournamentId,
        public StageId $stageId,
        public DateTimeImmutable $occurredAt,
    ) {}

    public function aggregateId(): string
    {
        return $this->stageId->value;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
