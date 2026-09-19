<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Infrastructure\Tournament;

use Tournaments\Scheduling\Domain\CompetitorId;
use Tournaments\Scheduling\Domain\MatchId;
use Tournaments\Scheduling\Domain\Planning\PlannableMatch;
use Tournaments\Scheduling\Domain\Planning\PlannableMatches;
use Tournaments\Scheduling\Domain\TournamentRef;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageRepository;
use Tournaments\Tournament\Domain\Tournament\TournamentId;

/**
 * Антикоррупционный слой между «Турниром» и «Расписанием»: переводит матчи стадий
 * на язык планировщика. Проходы без игры отбрасываются, ссылки на следующие матчи
 * превращаются в зависимости «этот матч не раньше тех».
 */
final readonly class TournamentPlannableMatches implements PlannableMatches
{
    public function __construct(
        private StageRepository $stages,
    ) {}

    public function of(TournamentRef $tournament): array
    {
        $stages = $this->stages->ofTournament(TournamentId::fromString($tournament->value));

        // Этап задаёт порядок, туры внутри этапа идут следом: группы одной фазы планируются параллельно.
        $matches = [];
        foreach ($stages as $stage) {
            foreach ($this->ofStage($stage, ($stage->phase - 1) * 100) as $match) {
                $matches[] = $match;
            }
        }

        return $matches;
    }

    /** @return list<PlannableMatch> */
    private function ofStage(Stage $stage, int $roundOffset): array
    {
        $playable = array_values(array_filter($stage->fixtures(), static fn (Fixture $f) => ! $f->isBye()));

        /** @var array<string, list<MatchId>> $dependencies */
        $dependencies = [];
        foreach ($playable as $fixture) {
            foreach ([$fixture->nextWin, $fixture->nextLose] as $slot) {
                if ($slot === null) {
                    continue;
                }
                $dependencies[$slot->fixtureId->value][] = MatchId::fromString($fixture->id->value);
            }
        }

        return array_map(static fn (Fixture $fixture) => new PlannableMatch(
            id: MatchId::fromString($fixture->id->value),
            order: $roundOffset + $fixture->round,
            sequence: $fixture->position,
            home: $fixture->home() === null ? null : CompetitorId::fromString($fixture->home()->value),
            away: $fixture->away() === null ? null : CompetitorId::fromString($fixture->away()->value),
            dependsOn: $dependencies[$fixture->id->value] ?? [],
        ), $playable);
    }
}
