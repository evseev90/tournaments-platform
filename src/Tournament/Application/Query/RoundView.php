<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Query;

final readonly class RoundView
{
    /** @param list<FixtureView> $fixtures */
    public function __construct(
        public int $number,
        public array $fixtures,
    ) {}
}
