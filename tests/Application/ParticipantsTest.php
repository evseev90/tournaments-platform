<?php

declare(strict_types=1);

namespace Tests\Application;

use PHPUnit\Framework\Attributes\Test;
use Tournaments\Tournament\Application\Command\AddParticipants;
use Tournaments\Tournament\Application\Command\RemoveParticipant;
use Tournaments\Tournament\Application\Command\ReorderParticipants;
use Tournaments\Tournament\Application\Command\ShuffleParticipants;
use Tournaments\Tournament\Application\Command\UpdateParticipant;
use Tournaments\Tournament\Application\Query\ParticipantView;
use Tournaments\Tournament\Domain\Event\ParticipantAdded;
use Tournaments\Tournament\Domain\Exception\InvalidName;
use Tournaments\Tournament\Domain\Exception\TournamentLocked;

final class ParticipantsTest extends ApplicationTestCase
{
    #[Test]
    public function adds_batch_in_given_order_with_members(): void
    {
        $created = $this->createdTournament('beach_volleyball');

        $ids = ($this->addParticipants)(new AddParticipants($created->id, 'pair', [
            ['name' => 'Иванов / Петров', 'members' => ['Иванов', 'Петров']],
            ['name' => 'Сидоров / Кузнецов', 'members' => ['Сидоров', 'Кузнецов']],
        ]));

        $participants = $this->view($created->id)->participants;
        self::assertCount(2, $ids);
        self::assertSame($ids, array_map(static fn (ParticipantView $p) => $p->id, $participants));
        self::assertSame([1, 2], array_map(static fn (ParticipantView $p) => $p->seed, $participants));
        self::assertSame('pair', $participants[0]->type);
        self::assertSame(['Иванов', 'Петров'], $participants[0]->members);
        self::assertCount(2, $this->events->of(ParticipantAdded::class));
    }

    #[Test]
    public function batch_is_all_or_nothing(): void
    {
        $created = $this->createdTournament();

        try {
            $this->addPlayers($created->id, ['Ok', '   ']);
            self::fail('expected InvalidName');
        } catch (InvalidName) {
        }

        self::assertSame([], $this->view($created->id)->participants);
    }

    #[Test]
    public function updates_removes_and_reorders(): void
    {
        $created = $this->createdTournament();
        [$a, $b, $c] = $this->addPlayers($created->id, ['A', 'B', 'C']);

        ($this->updateParticipant)(new UpdateParticipant($created->id, $a, 'A+'));
        ($this->removeParticipant)(new RemoveParticipant($created->id, $b));
        ($this->reorderParticipants)(new ReorderParticipants($created->id, [$c, $a]));

        $participants = $this->view($created->id)->participants;
        self::assertSame(['C', 'A+'], array_map(static fn (ParticipantView $p) => $p->name, $participants));
        self::assertSame([1, 2], array_map(static fn (ParticipantView $p) => $p->seed, $participants));
    }

    #[Test]
    public function shuffle_is_deterministic_for_seeded_randomizer(): void
    {
        $created = $this->createdTournament();
        $ids = $this->addPlayers($created->id, ['A', 'B', 'C', 'D', 'E', 'F']);

        ($this->shuffleParticipants)(new ShuffleParticipants($created->id));

        $after = array_map(static fn (ParticipantView $p) => $p->id, $this->view($created->id)->participants);
        self::assertNotSame($ids, $after);
        self::assertEqualsCanonicalizing($ids, $after);
    }

    #[Test]
    public function participants_are_locked_once_stage_exists(): void
    {
        [$tournamentId] = $this->runningTournament(3);

        $this->expectException(TournamentLocked::class);
        $this->addPlayers($tournamentId, ['Late']);
    }
}
