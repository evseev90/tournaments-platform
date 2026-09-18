<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

final readonly class RevertFixtureResult
{
    public function __construct(
        public string $stageId,
        public string $fixtureId,
    ) {}
}
