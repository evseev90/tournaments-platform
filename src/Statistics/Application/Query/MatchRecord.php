<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Application\Query;

final readonly class MatchRecord
{
    public function __construct(
        public string $tournamentName,
        public string $tournamentSlug,
        public string $stageName,
        public ?string $opponent,
        public string $result,
        public string $score,
        public string $playedAt,
    ) {}
}
