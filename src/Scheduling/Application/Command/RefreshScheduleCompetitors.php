<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Command;

/** Вызывается, когда в контексте «Турнир» стали известны участники следующих матчей. */
final readonly class RefreshScheduleCompetitors
{
    public function __construct(
        public string $tournamentId,
    ) {}
}
