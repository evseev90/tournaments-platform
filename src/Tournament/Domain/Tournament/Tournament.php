<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Tournament;

use DateTimeImmutable;
use Tournaments\Shared\Domain\DomainEvent;
use Tournaments\Shared\Domain\RecordsEvents;
use Tournaments\Tournament\Domain\Event\ParticipantAdded;
use Tournaments\Tournament\Domain\Event\ParticipantRemoved;
use Tournaments\Tournament\Domain\Event\TournamentCreated;
use Tournaments\Tournament\Domain\Event\TournamentFinished;
use Tournaments\Tournament\Domain\Event\TournamentReopened;
use Tournaments\Tournament\Domain\Event\TournamentStarted;
use Tournaments\Tournament\Domain\Exception\GroupsNotFinished;
use Tournaments\Tournament\Domain\Exception\NoStageGenerated;
use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Exception\ParticipantNotFound;
use Tournaments\Tournament\Domain\Exception\StageAlreadyGenerated;
use Tournaments\Tournament\Domain\Exception\StageHasResults;
use Tournaments\Tournament\Domain\Exception\StageNotFound;
use Tournaments\Tournament\Domain\Exception\TournamentAlreadyOwned;
use Tournaments\Tournament\Domain\Exception\TournamentLocked;
use Tournaments\Tournament\Domain\Scoring\MatchOutcome;
use Tournaments\Tournament\Domain\Scoring\ScoringRules;
use Tournaments\Tournament\Domain\Scoring\Sport;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\FixtureId;
use Tournaments\Tournament\Domain\Stage\Format\GroupSplitter;
use Tournaments\Tournament\Domain\Stage\Format\PlayoffSeeder;
use Tournaments\Tournament\Domain\Stage\Format\SwissPairing;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageFormat;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageSettings;

/**
 * Агрегат «Турнир»: название, вид спорта, правила счёта, участники, доступ, стадии.
 * Участники меняются только в статусе Draft: после генерации стадии состав заморожен.
 */
final class Tournament
{
    use RecordsEvents;

    public const int MIN_PARTICIPANTS = 2;

    /** @var array<string, Participant> */
    private array $participants = [];

    /** @var array<string, Stage> */
    private array $stages = [];

    /**
     * @param  list<Participant>  $participants
     * @param  list<Stage>  $stages
     */
    private function __construct(
        public readonly TournamentId $id,
        public private(set) Name $name,
        public readonly Sport $sport,
        public private(set) ScoringRules $rules,
        public private(set) TournamentStatus $status,
        public readonly AdminToken $adminToken,
        public readonly PublicSlug $publicSlug,
        public readonly DateTimeImmutable $createdAt,
        public private(set) ?OwnerId $ownerId,
        array $participants,
        array $stages = [],
    ) {
        foreach ($participants as $participant) {
            $this->participants[$participant->id->value] = $participant;
        }
        foreach ($stages as $stage) {
            $this->stages[$stage->id->value] = $stage;
        }
    }

    public static function create(
        TournamentId $id,
        Name $name,
        Sport $sport,
        ?ScoringRules $rules,
        DateTimeImmutable $now,
        ?OwnerId $ownerId = null,
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
            ownerId: $ownerId,
            participants: [],
        );
        $tournament->recordEvent(new TournamentCreated($id, $now));

        return $tournament;
    }

    /**
     * @param  list<Participant>  $participants
     * @param  list<Stage>  $stages
     */
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
        ?OwnerId $ownerId = null,
        array $stages = [],
    ): self {
        return new self($id, $name, $sport, $rules, $status, $adminToken, $publicSlug, $createdAt, $ownerId, $participants, $stages);
    }

    /**
     * Привязка турнира к аккаунту. Анонимный турнир может забрать себе тот, кто знает ссылку
     * организатора; чужой турнир забрать нельзя.
     */
    public function claimBy(OwnerId $owner): void
    {
        if ($this->ownerId !== null && ! $this->ownerId->equals($owner)) {
            throw new TournamentAlreadyOwned('У турнира уже есть владелец');
        }
        $this->ownerId = $owner;
    }

    public function isOwnedBy(?OwnerId $owner): bool
    {
        return $owner !== null && $this->ownerId?->equals($owner) === true;
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

    /** @return list<Stage> */
    public function stages(): array
    {
        return array_values($this->stages);
    }

    public function stage(StageId $id): Stage
    {
        return $this->stages[$id->value]
            ?? throw new StageNotFound(sprintf('Stage %s not found in tournament %s', $id, $this->id));
    }

    public function hasStage(StageId $id): bool
    {
        return isset($this->stages[$id->value]);
    }

    public function generateStage(
        StageId $stageId,
        StageFormat $format,
        StageSettings $settings,
        DateTimeImmutable $now,
    ): Stage {
        if ($this->stages !== []) {
            throw new StageAlreadyGenerated('The tournament already has a stage; discard it first');
        }

        $this->start($now);

        $stage = Stage::generate($stageId, $this->id, $format, $settings, $this->seededParticipantIds(), $now);
        $this->stages[$stageId->value] = $stage;

        return $stage;
    }

    /**
     * @return list<Stage>
     */
    public function generateGroups(
        int $groupsCount,
        StageSettings $settings,
        DateTimeImmutable $now,
        GroupSplitter $splitter = new GroupSplitter,
    ): array {
        if ($this->stages !== []) {
            throw new StageAlreadyGenerated('The tournament already has a stage; discard it first');
        }

        $split = $splitter->split($this->seededParticipantIds(), $groupsCount);
        $this->start($now);

        $stages = [];
        foreach ($split as $index => $members) {
            $stage = Stage::generate(
                id: StageId::generate(),
                tournamentId: $this->id,
                format: StageFormat::RoundRobin,
                settings: $settings,
                seeded: $members,
                now: $now,
                name: GroupSplitter::name($index),
            );
            $this->stages[$stage->id->value] = $stage;
            $stages[] = $stage;
        }

        return $stages;
    }

    public function generatePlayoff(
        int $advancePerGroup,
        bool $thirdPlaceMatch,
        DateTimeImmutable $now,
        PlayoffSeeder $seeder = new PlayoffSeeder,
    ): Stage {
        $existing = array_values($this->stages);
        $groups = array_values(array_filter($existing, static fn (Stage $s) => $s->phase === 1));

        if ($groups === []) {
            throw new NoStageGenerated('Сначала сыграйте групповой этап');
        }
        if (array_any($existing, static fn (Stage $s) => $s->phase >= 2)) {
            throw new StageAlreadyGenerated('Плей-офф уже сформирован');
        }
        if (array_any($groups, static fn (Stage $s) => ! $s->isCompleted())) {
            throw new GroupsNotFinished('Плей-офф можно собрать, только когда сыграны все матчи групп');
        }

        $tables = array_map(static fn (Stage $group) => $group->standings(), $groups);

        $stage = Stage::generate(
            id: StageId::generate(),
            tournamentId: $this->id,
            format: StageFormat::SingleElimination,
            settings: new StageSettings(thirdPlaceMatch: $thirdPlaceMatch),
            seeded: $seeder->seed($tables, $advancePerGroup),
            now: $now,
            name: 'Плей-офф',
            phase: 2,
        );
        $this->stages[$stage->id->value] = $stage;

        return $stage;
    }

    /**
     * @return list<Stage> отменённые стадии
     */
    public function discardCurrentStage(DateTimeImmutable $now): array
    {
        if ($this->stages === []) {
            throw new NoStageGenerated('The tournament has no stage to discard');
        }

        $lastPhase = max(array_map(static fn (Stage $s) => $s->phase, $this->stages));
        $doomed = array_values(array_filter($this->stages, static fn (Stage $s) => $s->phase === $lastPhase));

        foreach ($doomed as $stage) {
            if (array_any($stage->fixtures(), static fn (Fixture $f) => $f->isFinished())) {
                throw new StageHasResults('Cannot discard a stage with recorded results; revert them first');
            }
            unset($this->stages[$stage->id->value]);
        }

        if ($lastPhase === 1) {
            $this->reopen($now);
        }

        return $doomed;
    }

    public function pairNextRound(
        StageId $stageId,
        DateTimeImmutable $now,
        SwissPairing $pairing = new SwissPairing,
    ): void {
        $this->stage($stageId)->pairNextRound($pairing, $now);
    }

    public function recordFixtureResult(
        StageId $stageId,
        FixtureId $fixtureId,
        MatchOutcome $outcome,
        DateTimeImmutable $now,
    ): void {
        $this->stage($stageId)->recordResult($fixtureId, $outcome, $this->rules, $now);
    }

    public function revertFixtureResult(
        StageId $stageId,
        FixtureId $fixtureId,
        DateTimeImmutable $now,
    ): void {
        $this->stage($stageId)->revertResult($fixtureId, $now);
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        foreach ($this->stages as $stage) {
            $events = array_merge($events, $stage->releaseEvents());
        }

        return $events;
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
