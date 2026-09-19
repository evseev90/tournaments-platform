<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Query;

final readonly class VenueView
{
    public function __construct(
        public string $id,
        public string $name,
        public int $order,
    ) {}
}
