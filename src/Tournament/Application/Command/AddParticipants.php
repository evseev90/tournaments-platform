<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

/** Добавление одного или сразу списка участников: организатор обычно вставляет список из мессенджера. */
final readonly class AddParticipants
{
    /** @param list<array{name: string, members?: list<string>}> $participants */
    public function __construct(
        public string $tournamentId,
        public string $type,
        public array $participants,
    ) {}
}
