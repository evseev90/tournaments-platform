<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Domain\Roster;

/**
 * Порт в контекст «Турнир»: статистике нужен состав турнира и его владелец.
 * Реализация живёт в инфраструктуре и не тянет доменные классы турнира сюда.
 */
interface TournamentRoster
{
    /** @return list<RosterEntry> */
    public function participantsOf(string $tournamentId): array;

    public function ownerOf(string $tournamentId): ?int;

    /** @param array<string, string> $links участник => запись справочника */
    public function link(array $links): void;
}
