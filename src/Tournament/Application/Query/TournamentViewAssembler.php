<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Query;

use DateTimeInterface;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\FixtureSlot;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Tournament\Participant;
use Tournaments\Tournament\Domain\Tournament\Tournament;

final class TournamentViewAssembler
{
    /** @param list<Stage> $stages */
    public function assemble(Tournament $tournament, array $stages): TournamentView
    {
        return new TournamentView(
            id: $tournament->id->value,
            name: $tournament->name->value,
            sport: $tournament->sport->value,
            rules: $tournament->rules->toArray(),
            status: $tournament->status->value,
            publicSlug: $tournament->publicSlug->value,
            createdAt: $tournament->createdAt->format(DateTimeInterface::ATOM),
            participants: array_map($this->participant(...), $tournament->participants()),
            stages: array_map($this->stage(...), $stages),
        );
    }

    private function participant(Participant $participant): ParticipantView
    {
        return new ParticipantView(
            id: $participant->id->value,
            name: $participant->name->value,
            type: $participant->type->value,
            members: $participant->members,
            seed: $participant->seed,
        );
    }

    private function stage(Stage $stage): StageView
    {
        $rounds = [];
        for ($number = 1; $number <= $stage->roundsCount(); $number++) {
            $rounds[] = new RoundView($number, array_map($this->fixture(...), $stage->fixturesOfRound($number)));
        }

        return new StageView(
            id: $stage->id->value,
            format: $stage->format->value,
            settings: $stage->settings->toArray(),
            status: $stage->status->value,
            rounds: $rounds,
        );
    }

    private function fixture(Fixture $fixture): FixtureView
    {
        return new FixtureView(
            id: $fixture->id->value,
            round: $fixture->round,
            position: $fixture->position,
            homeId: $fixture->home()?->value,
            awayId: $fixture->away()?->value,
            status: $fixture->status()->value,
            outcome: $fixture->outcome()?->toArray(),
            winnerId: $fixture->winner()?->value,
            nextWin: $this->slot($fixture->nextWin),
            nextLose: $this->slot($fixture->nextLose),
        );
    }

    /** @return array{fixture_id: string, side: string}|null */
    private function slot(?FixtureSlot $slot): ?array
    {
        return $slot === null ? null : ['fixture_id' => $slot->fixtureId->value, 'side' => $slot->side->value];
    }
}
