<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage;

use Tournaments\Tournament\Domain\Tournament\ParticipantId;

/** Строка таблицы круговой стадии. Очки: 2 за победу, 1 за ничью. */
final class StandingRow
{
    public int $rank = 0;

    public int $played = 0;

    public int $wins = 0;

    public int $draws = 0;

    public int $losses = 0;

    public int $setsWon = 0;

    public int $setsLost = 0;

    public int $pointsWon = 0;

    public int $pointsLost = 0;

    /** Сумма очков соперников: в швейцарской системе это главный тайбрейк. */
    public int $buchholz = 0;

    public bool $hadBye = false;

    public function __construct(
        public readonly ParticipantId $participantId,
        public readonly int $seed,
    ) {}

    public function score(): int
    {
        return $this->wins * 2 + $this->draws;
    }

    public function setRatio(): float
    {
        return $this->setsLost === 0 ? ($this->setsWon === 0 ? 0.0 : INF) : $this->setsWon / $this->setsLost;
    }

    public function pointRatio(): float
    {
        return $this->pointsLost === 0 ? ($this->pointsWon === 0 ? 0.0 : INF) : $this->pointsWon / $this->pointsLost;
    }
}
