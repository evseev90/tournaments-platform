<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Query;

final readonly class ParticipantView
{
    /** @param list<string> $members */
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public array $members,
        public int $seed,
    ) {}
}
