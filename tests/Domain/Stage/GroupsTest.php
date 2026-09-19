<?php

declare(strict_types=1);

namespace Tests\Domain\Stage;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Scoring\SetBasedOutcome;
use Tournaments\Tournament\Domain\Scoring\SetBasedRules;
use Tournaments\Tournament\Domain\Stage\Format\GroupSplitter;
use Tournaments\Tournament\Domain\Stage\Format\PlayoffSeeder;
use Tournaments\Tournament\Domain\Stage\Format\SingleElimination;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageFormat;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Stage\StandingsCalculator;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\TournamentId;

final class GroupsTest extends TestCase
{
    /** @return iterable<string, array{int, int, list<list<int>>}> */
    public static function splits(): iterable
    {
        yield '4 into 2' => [4, 2, [[1, 4], [2, 3]]];
        yield '8 into 2' => [8, 2, [[1, 4, 5, 8], [2, 3, 6, 7]]];
        yield '8 into 4' => [8, 4, [[1, 8], [2, 7], [3, 6], [4, 5]]];
        yield '9 into 3' => [9, 3, [[1, 6, 7], [2, 5, 8], [3, 4, 9]]];
        yield '10 into 3' => [10, 3, [[1, 6, 7], [2, 5, 8], [3, 4, 9, 10]]];
    }

    /**
     * @param  list<list<int>>  $expected  номера посевов по группам
     */
    #[Test]
    #[DataProvider('splits')]
    public function snake_split_places_seeds_predictably(int $participants, int $groups, array $expected): void
    {
        $ids = array_map(static fn () => ParticipantId::generate(), range(1, $participants));
        $seedOf = [];
        foreach ($ids as $index => $id) {
            $seedOf[$id->value] = $index + 1;
        }

        $split = (new GroupSplitter)->split($ids, $groups);
        $asSeeds = array_map(
            static fn (array $group) => array_map(static fn (ParticipantId $id) => $seedOf[$id->value], $group),
            $split,
        );

        self::assertSame($expected, $asSeeds);
    }

    #[Test]
    public function snake_gives_strongest_to_different_groups(): void
    {
        $ids = array_map(static fn () => ParticipantId::generate(), range(1, 8));

        $split = (new GroupSplitter)->split($ids, 2);

        self::assertCount(2, $split);
        self::assertCount(4, $split[0]);
        self::assertTrue($split[0][0]->equals($ids[0]), 'первый сеяный в первую группу');
        self::assertTrue($split[1][0]->equals($ids[1]), 'второй сеяный во вторую');
        self::assertTrue($split[1][1]->equals($ids[2]), 'третий возвращается во вторую группу');
        self::assertTrue($split[0][1]->equals($ids[3]), 'четвёртый в первую');
    }

    #[Test]
    public function split_rejects_impossible_configurations(): void
    {
        $ids = array_map(static fn () => ParticipantId::generate(), range(1, 3));

        $this->expectException(NotEnoughParticipants::class);
        (new GroupSplitter)->split($ids, 2);
    }

    #[Test]
    public function group_names_are_letters(): void
    {
        self::assertSame('Группа A', GroupSplitter::name(0));
        self::assertSame('Группа D', GroupSplitter::name(3));
    }

    #[Test]
    public function winners_of_groups_meet_runners_up_of_other_groups(): void
    {
        $now = new DateTimeImmutable('2026-10-03');
        $rules = SetBasedRules::tableTennis();
        $ids = array_map(static fn () => ParticipantId::generate(), range(1, 8));
        $groups = (new GroupSplitter)->split($ids, 2);

        $standings = [];
        foreach ($groups as $index => $members) {
            $stage = Stage::generate(StageId::generate(), TournamentId::generate(), StageFormat::RoundRobin, StageSettings::default(), $members, $now, GroupSplitter::name($index));
            // Каждый обыгрывает всех, кто ниже него по посеву внутри группы.
            foreach ($stage->fixtures() as $fixture) {
                $homeIsStronger = array_search($fixture->home(), $members, false) < array_search($fixture->away(), $members, false);
                $set = $homeIsStronger ? [11, 3] : [3, 11];
                $stage->recordResult($fixture->id, SetBasedOutcome::of([$set, $set, $set]), $rules, $now);
            }
            $standings[] = (new StandingsCalculator)->calculate($members, $stage->fixtures());
        }

        $seeded = (new PlayoffSeeder)->seed($standings, advancePerGroup: 2);

        self::assertCount(4, $seeded);
        self::assertTrue($seeded[0]->equals($groups[0][0]), 'первый посев — победитель группы A');
        self::assertTrue($seeded[1]->equals($groups[1][0]), 'второй посев — победитель группы B');

        $playoff = Stage::generate(StageId::generate(), TournamentId::generate(), StageFormat::SingleElimination, StageSettings::default(), $seeded, $now, 'Плей-офф', 2);
        $semis = $playoff->fixturesOfRound(1);

        self::assertSame('Плей-офф', $playoff->name);
        self::assertSame(2, $playoff->phase);
        self::assertTrue($semis[0]->home()?->equals($groups[0][0]) ?? false);
        self::assertTrue($semis[0]->away()?->equals($groups[1][1]) ?? false, 'победитель A против второго места B');
        self::assertTrue($semis[1]->home()?->equals($groups[1][0]) ?? false);
        self::assertTrue($semis[1]->away()?->equals($groups[0][1]) ?? false, 'победитель B против второго места A');
    }

    #[Test]
    public function seeder_needs_enough_places_in_every_group(): void
    {
        $now = new DateTimeImmutable('2026-10-03');
        $members = [ParticipantId::generate(), ParticipantId::generate()];
        $stage = Stage::generate(StageId::generate(), TournamentId::generate(), StageFormat::RoundRobin, StageSettings::default(), $members, $now);
        $standings = [(new StandingsCalculator)->calculate($members, $stage->fixtures())];

        $this->expectException(NotEnoughParticipants::class);
        (new PlayoffSeeder)->seed($standings, advancePerGroup: 3);
    }

    #[Test]
    public function stage_knows_its_own_participants(): void
    {
        $now = new DateTimeImmutable('2026-10-03');
        $members = array_map(static fn () => ParticipantId::generate(), range(1, 4));

        $stage = Stage::generate(StageId::generate(), TournamentId::generate(), StageFormat::RoundRobin, StageSettings::default(), $members, $now, 'Группа A');

        self::assertEqualsCanonicalizing(
            array_map(static fn (ParticipantId $id) => $id->value, $members),
            array_map(static fn (ParticipantId $id) => $id->value, $stage->participants()),
        );
        self::assertSame('Группа A', $stage->name);
        self::assertSame(1, $stage->phase);
    }

    #[Test]
    public function playoff_bracket_size_follows_advancing_count(): void
    {
        self::assertSame(8, SingleElimination::bracketSize(6), 'шесть вышедших играют сетку на восемь с проходами');
    }
}
