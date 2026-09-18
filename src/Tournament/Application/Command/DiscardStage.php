<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

/** Удаляет сетку и возвращает турнир в набор участников. Возможно, пока не сыгран ни один матч. */
final readonly class DiscardStage
{
    public function __construct(
        public string $tournamentId,
    ) {}
}
