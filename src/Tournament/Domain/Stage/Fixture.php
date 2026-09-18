<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage;

use Tournaments\Tournament\Domain\Scoring\MatchOutcome;
use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;

/**
 * Матч внутри стадии. Назван Fixture, потому что match — зарезервированное слово PHP.
 * Меняется только через агрегат Stage.
 */
final class Fixture
{
    public function __construct(
        public readonly FixtureId $id,
        public readonly int $round,
        public readonly int $position,
        private ?ParticipantId $home,
        private ?ParticipantId $away,
        private ?MatchOutcome $outcome = null,
        private bool $bye = false,
        public readonly ?FixtureSlot $nextWin = null,
        public readonly ?FixtureSlot $nextLose = null,
    ) {}

    public function participant(Side $side): ?ParticipantId
    {
        return $side === Side::Home ? $this->home : $this->away;
    }

    public function home(): ?ParticipantId
    {
        return $this->home;
    }

    public function away(): ?ParticipantId
    {
        return $this->away;
    }

    public function outcome(): ?MatchOutcome
    {
        return $this->outcome;
    }

    public function status(): FixtureStatus
    {
        return match (true) {
            $this->bye => FixtureStatus::Bye,
            $this->outcome !== null => FixtureStatus::Finished,
            $this->home !== null && $this->away !== null => FixtureStatus::Ready,
            default => FixtureStatus::Pending,
        };
    }

    public function isBye(): bool
    {
        return $this->bye;
    }

    public function isFinished(): bool
    {
        return $this->outcome !== null;
    }

    public function isSettled(): bool
    {
        return $this->bye || $this->outcome !== null;
    }

    public function isReady(): bool
    {
        return $this->status() === FixtureStatus::Ready;
    }

    public function winner(): ?ParticipantId
    {
        if ($this->bye) {
            return $this->home ?? $this->away;
        }
        $side = $this->outcome?->winner();

        return $side === null ? null : $this->participant($side);
    }

    public function loser(): ?ParticipantId
    {
        if ($this->bye) {
            return null;
        }
        $side = $this->outcome?->winner();

        return $side === null ? null : $this->participant($side->opposite());
    }

    public function sideOf(ParticipantId $participant): ?Side
    {
        return match (true) {
            $participant->equals($this->home) => Side::Home,
            $participant->equals($this->away) => Side::Away,
            default => null,
        };
    }

    /** @internal только для Stage и генераторов */
    public function assign(Side $side, ?ParticipantId $participant): void
    {
        if ($side === Side::Home) {
            $this->home = $participant;
        } else {
            $this->away = $participant;
        }
    }

    /** @internal только для генераторов */
    public function markBye(): void
    {
        $this->bye = true;
    }

    /** @internal только для Stage */
    public function finish(MatchOutcome $outcome): void
    {
        $this->outcome = $outcome;
    }

    /** @internal только для Stage */
    public function reopen(): void
    {
        $this->outcome = null;
    }
}
