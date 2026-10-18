<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Tournament;

use Tournaments\Tournament\Domain\Stage\StageId;

interface TournamentRepository
{
    public function get(TournamentId $id): Tournament;

    public function findByAdminToken(AdminToken $token): ?Tournament;

    public function findBySlug(PublicSlug $slug): ?Tournament;

    public function findByStageId(StageId $stageId): ?Tournament;

    public function save(Tournament $tournament): void;
}
