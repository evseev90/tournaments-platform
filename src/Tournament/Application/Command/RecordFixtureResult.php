<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

final readonly class RecordFixtureResult
{
    /** @param array<string, mixed> $outcome см. ScoringRulesFactory::outcomeFromArray */
    public function __construct(
        public string $tournamentId,
        public string $stageId,
        public string $fixtureId,
        public array $outcome,
    ) {}
}
