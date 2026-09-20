<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage\Format;

use Tournaments\Tournament\Domain\Tournament\ParticipantId;

/**
 * Положение участника перед жеребьёвкой очередного тура.
 *
 * @internal используется только внутри SwissPairing
 */
final class SwissStandingSnapshot
{
    public int $score = 0;

    public int $homeGames = 0;

    public bool $hadBye = false;

    /** @var array<string, true> */
    public array $opponents = [];

    public function __construct(
        public readonly ParticipantId $id,
        public readonly int $seed,
    ) {}

    public function hasPlayed(ParticipantId $other): bool
    {
        return isset($this->opponents[$other->value]);
    }
}
