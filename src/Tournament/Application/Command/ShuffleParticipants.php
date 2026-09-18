<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

/** Случайный посев: жеребьёвка. */
final readonly class ShuffleParticipants
{
    public function __construct(
        public string $tournamentId,
    ) {}
}
