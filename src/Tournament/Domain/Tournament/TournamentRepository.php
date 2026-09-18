<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Tournament;

interface TournamentRepository
{
    public function get(TournamentId $id): Tournament;

    public function findByAdminToken(AdminToken $token): ?Tournament;

    public function findBySlug(PublicSlug $slug): ?Tournament;

    public function save(Tournament $tournament): void;
}
