<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Event;

use DateTimeImmutable;
use Tournaments\Shared\Domain\DomainEvent;
use Tournaments\Tournament\Domain\Tournament\TournamentId;

/** Сетка отброшена, турнир вернулся в набор участников. */
final readonly class TournamentReopened implements DomainEvent
{
    public function __construct(
        public TournamentId $tournamentId,
        public DateTimeImmutable $occurredAt,
    ) {}

    public function aggregateId(): string
    {
        return $this->tournamentId->value;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
