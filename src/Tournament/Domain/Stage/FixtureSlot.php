<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage;

use Tournaments\Tournament\Domain\Scoring\Side;

/** Куда попадает участник после матча: в какой матч и на какую сторону. */
final readonly class FixtureSlot
{
    public function __construct(
        public FixtureId $fixtureId,
        public Side $side,
    ) {}
}
