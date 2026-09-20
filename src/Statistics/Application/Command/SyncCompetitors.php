<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Application\Command;

/** Приводит справочник аккаунта в соответствие с составом турнира. */
final readonly class SyncCompetitors
{
    public function __construct(
        public string $tournamentId,
    ) {}
}
