<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Domain;

use DateTimeImmutable;

/**
 * Матч на площадке в конкретное время. Участники здесь продублированы намеренно:
 * без них агрегат не сможет проверить, что никто не играет в двух местах сразу.
 */
final class Assignment
{
    public function __construct(
        public readonly MatchId $matchId,
        public private(set) VenueId $venueId,
        public private(set) DateTimeImmutable $startsAt,
        public private(set) int $minutes,
        public private(set) ?ParticipantRef $home = null,
        public private(set) ?ParticipantRef $away = null,
    ) {}

    public function endsAt(): DateTimeImmutable
    {
        return $this->startsAt->modify(sprintf('+%d minutes', $this->minutes));
    }

    public function moveTo(VenueId $venueId, DateTimeImmutable $startsAt): void
    {
        $this->venueId = $venueId;
        $this->startsAt = $startsAt;
    }

    public function withParticipants(?ParticipantRef $home, ?ParticipantRef $away): void
    {
        $this->home = $home;
        $this->away = $away;
    }

    public function involves(?ParticipantRef $competitor): bool
    {
        return $competitor !== null && ($competitor->equals($this->home) || $competitor->equals($this->away));
    }

    /** Пересекается ли по времени с отрезком другого матча. */
    public function overlaps(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): bool
    {
        return $this->startsAt < $endsAt && $startsAt < $this->endsAt();
    }
}
