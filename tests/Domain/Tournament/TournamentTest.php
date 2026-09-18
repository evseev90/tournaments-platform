<?php

declare(strict_types=1);

namespace Tests\Domain\Tournament;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tournaments\Tournament\Domain\Event\ParticipantAdded;
use Tournaments\Tournament\Domain\Event\TournamentCreated;
use Tournaments\Tournament\Domain\Event\TournamentStarted;
use Tournaments\Tournament\Domain\Exception\InvalidName;
use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Exception\ParticipantNotFound;
use Tournaments\Tournament\Domain\Exception\TournamentLocked;
use Tournaments\Tournament\Domain\Scoring\SetBasedRules;
use Tournaments\Tournament\Domain\Scoring\Sport;
use Tournaments\Tournament\Domain\Tournament\Name;
use Tournaments\Tournament\Domain\Tournament\Participant;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\ParticipantType;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentStatus;

final class TournamentTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-09-18 12:00:00');
    }

    #[Test]
    public function creates_draft_with_sport_defaults_and_access_keys(): void
    {
        $tournament = Tournament::create(TournamentId::generate(), new Name('  Кубок  клуба '), Sport::BeachVolleyball, null, $this->now);

        self::assertSame('Кубок клуба', $tournament->name->value);
        self::assertSame(TournamentStatus::Draft, $tournament->status);
        self::assertEquals(SetBasedRules::beachVolleyball(), $tournament->rules);
        self::assertSame(40, strlen($tournament->adminToken->value));
        self::assertSame(10, strlen($tournament->publicSlug->value));
        self::assertInstanceOf(TournamentCreated::class, $tournament->releaseEvents()[0]);
    }

    #[Test]
    public function rejects_empty_name(): void
    {
        $this->expectException(InvalidName::class);

        new Name('   ');
    }

    #[Test]
    public function participants_are_seeded_in_order_of_addition(): void
    {
        $tournament = $this->draft();
        $a = $this->add($tournament, 'Alpha');
        $b = $this->add($tournament, 'Beta', ParticipantType::Pair, ['Иван', 'Пётр', '']);

        self::assertSame(1, $tournament->participant($a)->seed);
        self::assertSame(2, $tournament->participant($b)->seed);
        self::assertSame(['Иван', 'Пётр'], $tournament->participant($b)->members);
        self::assertContainsOnlyInstancesOf(ParticipantAdded::class, array_slice($tournament->releaseEvents(), 1));
    }

    #[Test]
    public function removing_reseeds_the_rest(): void
    {
        $tournament = $this->draft();
        $a = $this->add($tournament, 'A');
        $b = $this->add($tournament, 'B');
        $c = $this->add($tournament, 'C');

        $tournament->removeParticipant($b, $this->now);

        self::assertSame([1, 2], array_map(static fn (Participant $p) => $p->seed, $tournament->participants()));
        self::assertEquals([$a, $c], $tournament->seededParticipantIds());
        $this->expectException(ParticipantNotFound::class);
        $tournament->participant($b);
    }

    #[Test]
    public function reordering_sets_seeds(): void
    {
        $tournament = $this->draft();
        $a = $this->add($tournament, 'A');
        $b = $this->add($tournament, 'B');
        $c = $this->add($tournament, 'C');

        $tournament->reorderParticipants([$c, $a, $b]);

        self::assertEquals([$c, $a, $b], $tournament->seededParticipantIds());
        self::assertSame(1, $tournament->participant($c)->seed);
    }

    #[Test]
    public function reordering_must_include_everyone(): void
    {
        $tournament = $this->draft();
        $a = $this->add($tournament, 'A');
        $this->add($tournament, 'B');

        $this->expectException(ParticipantNotFound::class);
        $tournament->reorderParticipants([$a]);
    }

    #[Test]
    public function needs_two_participants_to_start(): void
    {
        $tournament = $this->draft();
        $this->add($tournament, 'Lonely');

        $this->expectException(NotEnoughParticipants::class);
        $tournament->start($this->now);
    }

    #[Test]
    public function start_freezes_participants_and_rules(): void
    {
        $tournament = $this->draft();
        $a = $this->add($tournament, 'A');
        $this->add($tournament, 'B');

        $tournament->start($this->now);

        self::assertSame(TournamentStatus::InProgress, $tournament->status);
        self::assertInstanceOf(TournamentStarted::class, array_last($tournament->releaseEvents()));

        $this->expectException(TournamentLocked::class);
        $tournament->removeParticipant($a, $this->now);
    }

    #[Test]
    public function rules_can_be_customised_while_draft(): void
    {
        $tournament = $this->draft();
        $custom = new SetBasedRules(setsToWin: 2, pointsPerSet: 15, pointsInDecidingSet: 15);

        $tournament->changeRules($custom);

        self::assertSame($custom, $tournament->rules);
    }

    private function draft(): Tournament
    {
        return Tournament::create(TournamentId::generate(), new Name('Test'), Sport::Volleyball, null, $this->now);
    }

    /** @param list<string> $members */
    private function add(Tournament $tournament, string $name, ParticipantType $type = ParticipantType::Team, array $members = []): ParticipantId
    {
        $id = ParticipantId::generate();
        $tournament->addParticipant($id, new Name($name), $type, $members, $this->now);

        return $id;
    }
}
