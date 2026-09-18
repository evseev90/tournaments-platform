<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Tournament;

use DateTimeImmutable;
use Tournaments\Shared\Domain\RecordsEvents;
use Tournaments\Tournament\Domain\Event\ParticipantAdded;
use Tournaments\Tournament\Domain\Event\ParticipantRemoved;
use Tournaments\Tournament\Domain\Event\TournamentCreated;
use Tournaments\Tournament\Domain\Event\TournamentFinished;
use Tournaments\Tournament\Domain\Event\TournamentReopened;
use Tournaments\Tournament\Domain\Event\TournamentStarted;
use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Exception\ParticipantNotFound;
use Tournaments\Tournament\Domain\Exception\TournamentLocked;
use Tournaments\Tournament\Domain\Scoring\ScoringRules;
use Tournaments\Tournament\Domain\Scoring\Sport;

/**
 * Агрегат «Турнир»: название, вид спорта, правила счёта, участники, доступ.
 * Участники меняются только в статусе Draft: после генерации стадии состав заморожен.
 */
final class Tournament
{
    use RecordsEvents;

    public const int MIN_PARTICIPANTS = 2;

    /** @var array<string, Participant> */
    private array $participants = [];

    /** @param list<Participant> $participants */
    private function __construct(
        public readonly TournamentId $id,
        public private(set) Name $name,
        public readonly Sport $sport,
        public private(set) ScoringRules $rules,
        public private(set) TournamentStatus $status,
        public readonly AdminToken $adminToken,
        public readonly PublicSlug $publicSlug,
        public readonly DateTimeImmutable $createdAt,
        array $participants,
    ) {
        foreach ($participants as $participant) {
            $this->participants[$participant->id->value] = $participant;
        }
    }

    public static function create(
        TournamentId $id,
        Name $name,
        Sport $sport,
        ?ScoringRules $rules,
        DateTimeImmutable $now,
    ): self {
        $tournament = new self(
            id: $id,
            name: $name,
            sport: $sport,
            rules: $rules ?? $sport->defaultRules(),
            status: TournamentStatus::Draft,
            adminToken: AdminToken::generate(),
            publicSlug: PublicSlug::generate(),
            createdAt: $now,
            participants: [],
        );
        $tournament->recordEvent(new TournamentCreated($id, $now));

        return $tournament;
    }

    /** @param list<Participant> $participants */
    public static function reconstitute(
        TournamentId $id,
        Name $name,
        Sport $sport,
        ScoringRules $rules,
        TournamentStatus $status,
        AdminToken $adminToken,
        PublicSlug $publicSlug,
        DateTimeImmutable $createdAt,
        array $participants,
    ): self {
        return new self($id, $name, $sport, $rules, $status, $adminToken, $publicSlug, $createdAt, $participants);
    }

    public function rename(Name $name): void
    {
        $this->name = $name;
    }

    public function changeRules(ScoringRules $rules): void
    {
        $this->assertDraft();
        $this->rules = $rules;
    }

    /** @param list<string> $members */
    public function addParticipant(
        ParticipantId $id,
        Name $name,
        ParticipantType $type,
        array $members,
        DateTimeImmutable $now,
    ): Participant {
        $this->assertDraft();

        $participant = new Participant($id, $name, $type, $members, seed: count($this->participants) + 1);
        $this->participants[$id->value] = $participant;
        $this->recordEvent(new ParticipantAdded($this->id, $id, $now));

        return $participant;
    }

    /** @param list<string> $members */
    public function updateParticipant(ParticipantId $id, Name $name, array $members): void
    {
        $this->assertDraft();
        $this->participant($id)->update($name, $members);
    }

    public function removeParticipant(ParticipantId $id, DateTimeImmutable $now): void
    {
        $this->assertDraft();
        $this->participant($id);
        unset($this->participants[$id->value]);
        $this->reseedSequentially(array_values($this->participants));
        $this->recordEvent(new ParticipantRemoved($this->id, $id, $now));
    }

    /**
     * Задаёт посев порядком списка: первый — первый сеяный. Список должен содержать всех участников.
     *
     * @param  list<ParticipantId>  $order
     */
    public function reorderParticipants(array $order): void
    {
        $this->assertDraft();

        $ordered = [];
        foreach ($order as $id) {
            $ordered[$id->value] = $this->participant($id);
        }
        if (count($ordered) !== count($this->participants)) {
            throw new ParticipantNotFound('Reorder must include every participant exactly once');
        }
        $this->reseedSequentially(array_values($ordered));
    }

    /** Замораживает состав. Вызывается при генерации первой стадии. */
    public function start(DateTimeImmutable $now): void
    {
        $this->assertDraft();
        if (count($this->participants) < self::MIN_PARTICIPANTS) {
            throw new NotEnoughParticipants(sprintf('At least %d participants are required', self::MIN_PARTICIPANTS));
        }
        $this->status = TournamentStatus::InProgress;
        $this->recordEvent(new TournamentStarted($this->id, $now));
    }

    public function finish(DateTimeImmutable $now): void
    {
        if ($this->status !== TournamentStatus::InProgress) {
            throw new TournamentLocked('Only a tournament in progress can be finished');
        }
        $this->status = TournamentStatus::Finished;
        $this->recordEvent(new TournamentFinished($this->id, $now));
    }

    /** Возврат в набор участников после отмены сетки. Проверка, что результатов нет, лежит на вызывающем. */
    public function reopen(DateTimeImmutable $now): void
    {
        if ($this->status !== TournamentStatus::InProgress) {
            throw new TournamentLocked('Only a tournament in progress can be reopened');
        }
        $this->status = TournamentStatus::Draft;
        $this->recordEvent(new TournamentReopened($this->id, $now));
    }

    public function participant(ParticipantId $id): Participant
    {
        return $this->participants[$id->value]
            ?? throw new ParticipantNotFound(sprintf('Participant %s not found in tournament %s', $id, $this->id));
    }

    /**
     * Участники в порядке посева.
     *
     * @return list<Participant>
     */
    public function participants(): array
    {
        $list = array_values($this->participants);
        usort($list, static fn (Participant $a, Participant $b) => $a->seed <=> $b->seed);

        return $list;
    }

    /**
     * Идентификаторы участников в порядке посева.
     *
     * @return list<ParticipantId>
     */
    public function seededParticipantIds(): array
    {
        return array_map(static fn (Participant $p) => $p->id, $this->participants());
    }

    public function isDraft(): bool
    {
        return $this->status === TournamentStatus::Draft;
    }

    private function assertDraft(): void
    {
        if (! $this->isDraft()) {
            throw new TournamentLocked('Participants and rules are locked once the tournament has started');
        }
    }

    /** @param list<Participant> $ordered */
    private function reseedSequentially(array $ordered): void
    {
        $this->participants = [];
        foreach ($ordered as $index => $participant) {
            $participant->reseed($index + 1);
            $this->participants[$participant->id->value] = $participant;
        }
    }
}
