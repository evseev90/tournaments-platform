<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Domain\Roster;

/**
 * Участник турнира глазами статистики: кто это, каким составом выступает
 * и к какому аккаунту относится.
 */
final readonly class RosterEntry
{
    public function __construct(
        public string $participantId,
        public string $name,
        public CompetitorType $type,
    ) {}
}
