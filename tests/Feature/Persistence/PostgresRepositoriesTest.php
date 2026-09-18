<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tournaments\Tournament\Domain\Exception\StageNotFound;
use Tournaments\Tournament\Domain\Exception\TournamentNotFound;
use Tournaments\Tournament\Domain\Scoring\SetBasedOutcome;
use Tournaments\Tournament\Domain\Scoring\SetBasedRules;
use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Domain\Scoring\Sport;
use Tournaments\Tournament\Domain\Scoring\Walkover;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageFormat;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageRepository;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Tournament\Name;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\ParticipantType;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

final class PostgresRepositoriesTest extends TestCase
{
    use RefreshDatabase;

    private TournamentRepository $tournaments;

    private StageRepository $stages;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tournaments = $this->app->make(TournamentRepository::class);
        $this->stages = $this->app->make(StageRepository::class);
        $this->now = new DateTimeImmutable('2026-09-18 12:00:00+00:00');
    }

    #[Test]
    public function tournament_round_trips_with_participants_and_rules(): void
    {
        $tournament = Tournament::create(TournamentId::generate(), new Name('Кубок'), Sport::BeachVolleyball, null, $this->now);
        $a = ParticipantId::generate();
        $b = ParticipantId::generate();
        $tournament->addParticipant($a, new Name('Иванов / Петров'), ParticipantType::Pair, ['Иванов', 'Петров'], $this->now);
        $tournament->addParticipant($b, new Name('Сидоровы'), ParticipantType::Pair, [], $this->now);
        $tournament->reorderParticipants([$b, $a]);

        $this->tournaments->save($tournament);
        $loaded = $this->tournaments->get($tournament->id);

        self::assertSame('Кубок', $loaded->name->value);
        self::assertSame(Sport::BeachVolleyball, $loaded->sport);
        self::assertEquals(SetBasedRules::beachVolleyball(), $loaded->rules);
        self::assertTrue($loaded->adminToken->equals($tournament->adminToken));
        self::assertTrue($loaded->publicSlug->equals($tournament->publicSlug));
        self::assertEquals($this->now, $loaded->createdAt);
        self::assertEquals([$b, $a], $loaded->seededParticipantIds());
        self::assertSame(['Иванов', 'Петров'], $loaded->participant($a)->members);
        self::assertSame([], $loaded->participant($b)->members);
        self::assertSame(ParticipantType::Pair, $loaded->participant($a)->type);
    }

    #[Test]
    public function saving_again_updates_and_deletes_participants(): void
    {
        $tournament = Tournament::create(TournamentId::generate(), new Name('Кубок'), Sport::TableTennis, null, $this->now);
        $a = ParticipantId::generate();
        $b = ParticipantId::generate();
        $tournament->addParticipant($a, new Name('A'), ParticipantType::Player, [], $this->now);
        $tournament->addParticipant($b, new Name('B'), ParticipantType::Player, [], $this->now);
        $this->tournaments->save($tournament);

        $loaded = $this->tournaments->get($tournament->id);
        $loaded->removeParticipant($a, $this->now);
        $loaded->updateParticipant($b, new Name('B renamed'), ['x']);
        $loaded->rename(new Name('Переименован'));
        $loaded->changeRules(new SetBasedRules(2, 11, 11));
        $this->tournaments->save($loaded);

        $again = $this->tournaments->get($tournament->id);
        self::assertSame('Переименован', $again->name->value);
        self::assertSame(2, $again->rules instanceof SetBasedRules ? $again->rules->setsToWin : null);
        self::assertCount(1, $again->participants());
        self::assertSame('B renamed', $again->participant($b)->name->value);
        self::assertSame(1, $again->participant($b)->seed);
        self::assertSame(1, $this->app->make('db')->table('participants')->count());
    }

    #[Test]
    public function finds_by_token_and_slug_only(): void
    {
        $tournament = Tournament::create(TournamentId::generate(), new Name('Кубок'), Sport::Volleyball, null, $this->now);
        $this->tournaments->save($tournament);

        self::assertTrue($this->tournaments->findByAdminToken($tournament->adminToken)?->id->equals($tournament->id) ?? false);
        self::assertTrue($this->tournaments->findBySlug($tournament->publicSlug)?->id->equals($tournament->id) ?? false);

        $this->expectException(TournamentNotFound::class);
        $this->tournaments->get(TournamentId::generate());
    }

    #[Test]
    public function stage_round_trips_with_links_byes_and_outcomes(): void
    {
        $tournament = Tournament::create(TournamentId::generate(), new Name('Кубок'), Sport::TableTennis, null, $this->now);
        $ids = [];
        foreach (['A', 'B', 'C', 'D', 'E'] as $name) {
            $id = ParticipantId::generate();
            $tournament->addParticipant($id, new Name($name), ParticipantType::Player, [], $this->now);
            $ids[] = $id;
        }
        $tournament->start($this->now);
        $this->tournaments->save($tournament);

        $stage = Stage::generate(StageId::generate(), $tournament->id, StageFormat::SingleElimination, new StageSettings(thirdPlaceMatch: true), $ids, $this->now);
        $readyFixture = array_find($stage->fixturesOfRound(1), static fn (Fixture $f) => $f->isReady());
        self::assertNotNull($readyFixture);
        $stage->recordResult($readyFixture->id, SetBasedOutcome::of([[11, 5], [11, 5], [11, 5]]), $tournament->rules, $this->now);
        $this->stages->save($stage);

        $loaded = $this->stages->get($stage->id);

        self::assertSame($stage->format, $loaded->format);
        self::assertTrue($loaded->settings->thirdPlaceMatch);
        self::assertSame($stage->status, $loaded->status);
        self::assertCount(count($stage->fixtures()), $loaded->fixtures());
        foreach ($stage->fixtures() as $index => $original) {
            $copy = $loaded->fixtures()[$index];
            self::assertTrue($original->id->equals($copy->id));
            self::assertSame($original->status(), $copy->status());
            self::assertTrue($original->home()?->equals($copy->home()) ?? $copy->home() === null);
            self::assertTrue($original->away()?->equals($copy->away()) ?? $copy->away() === null);
            self::assertTrue($original->nextWin?->fixtureId->equals($copy->nextWin?->fixtureId) ?? $copy->nextWin === null);
            self::assertSame($original->nextWin?->side, $copy->nextWin?->side);
            self::assertTrue($original->nextLose?->fixtureId->equals($copy->nextLose?->fixtureId) ?? $copy->nextLose === null);
            self::assertTrue($original->outcome()?->equals($copy->outcome() ?? new Walkover(Side::Home)) ?? $copy->outcome() === null);
        }
        self::assertEquals([$loaded], $this->stages->ofTournament($tournament->id));
    }

    #[Test]
    public function removing_stage_deletes_fixtures(): void
    {
        $tournament = Tournament::create(TournamentId::generate(), new Name('Кубок'), Sport::TableTennis, null, $this->now);
        $ids = [];
        foreach (['A', 'B', 'C'] as $name) {
            $id = ParticipantId::generate();
            $tournament->addParticipant($id, new Name($name), ParticipantType::Player, [], $this->now);
            $ids[] = $id;
        }
        $this->tournaments->save($tournament);
        $stage = Stage::generate(StageId::generate(), $tournament->id, StageFormat::RoundRobin, StageSettings::default(), $ids, $this->now);
        $this->stages->save($stage);

        $this->stages->remove($stage);

        self::assertSame(0, $this->app->make('db')->table('fixtures')->count());
        self::assertSame([], $this->stages->ofTournament($tournament->id));
        $this->expectException(StageNotFound::class);
        $this->stages->get($stage->id);
    }
}
