<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

/** Жеребьёвка очередного тура швейцарской системы. */
final readonly class PairNextRound
{
    public function __construct(
        public string $tournamentId,
        public string $stageId,
    ) {}
}
