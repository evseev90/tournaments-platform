<?php

declare(strict_types=1);

namespace Tests\Support;

use Tournaments\Tournament\Domain\Exception\TournamentNotFound;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Tournament\AdminToken;
use Tournaments\Tournament\Domain\Tournament\PublicSlug;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final class InMemoryTournamentRepository implements TournamentRepository
{
    /** @var array<string, Tournament> */
    private array $items = [];

    public function get(TournamentId $id): Tournament
    {
        return self::copy($this->items[$id->value] ?? throw new TournamentNotFound(sprintf('Tournament %s not found', $id)));
    }

    public function findByAdminToken(AdminToken $token): ?Tournament
    {
        $found = array_find($this->items, static fn (Tournament $t) => $t->adminToken->equals($token));

        return $found === null ? null : self::copy($found);
    }

    public function findBySlug(PublicSlug $slug): ?Tournament
    {
        $found = array_find($this->items, static fn (Tournament $t) => $t->publicSlug->equals($slug));

        return $found === null ? null : self::copy($found);
    }

    public function findByStageId(StageId $stageId): ?Tournament
    {
        $found = array_find($this->items, static fn (Tournament $t) => $t->hasStage($stageId));

        return $found === null ? null : self::copy($found);
    }

    public function save(Tournament $tournament): void
    {
        $this->items[$tournament->id->value] = self::copy($tournament);
    }

    /** Глубокая копия: как настоящая база, хранилище не делит объекты с вызывающим кодом. */
    private static function copy(Tournament $tournament): Tournament
    {
        $copy = unserialize(serialize($tournament));
        assert($copy instanceof Tournament);

        return $copy;
    }
}
