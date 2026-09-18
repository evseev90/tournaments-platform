<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage;

use Tournaments\Tournament\Domain\Tournament\TournamentId;

interface StageRepository
{
    public function get(StageId $id): Stage;

    /** @return list<Stage> */
    public function ofTournament(TournamentId $tournamentId): array;

    public function save(Stage $stage): void;
}
