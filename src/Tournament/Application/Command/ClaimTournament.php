<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

/** Привязка анонимного турнира к аккаунту организатора. */
final readonly class ClaimTournament
{
    public function __construct(
        public string $tournamentId,
        public int $ownerId,
    ) {}
}
