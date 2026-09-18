<?php

declare(strict_types=1);

namespace Tests\Support;

use Tournaments\Tournament\Domain\Exception\StageNotFound;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageRepository;
use Tournaments\Tournament\Domain\Tournament\TournamentId;

final class InMemoryStageRepository implements StageRepository
{
    /** @var array<string, Stage> */
    private array $items = [];

    public function get(StageId $id): Stage
    {
        return self::copy($this->items[$id->value] ?? throw new StageNotFound(sprintf('Stage %s not found', $id)));
    }

    public function ofTournament(TournamentId $tournamentId): array
    {
        return array_values(array_map(self::copy(...), array_filter($this->items, static fn (Stage $s) => $s->tournamentId->equals($tournamentId))));
    }

    public function save(Stage $stage): void
    {
        $this->items[$stage->id->value] = self::copy($stage);
    }

    private static function copy(Stage $stage): Stage
    {
        $copy = unserialize(serialize($stage));
        assert($copy instanceof Stage);

        return $copy;
    }

    public function remove(Stage $stage): void
    {
        unset($this->items[$stage->id->value]);
    }
}
