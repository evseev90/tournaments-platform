<?php

declare(strict_types=1);

namespace Tests\Application;

use PHPUnit\Framework\Attributes\Test;
use Tournaments\Tournament\Application\Command\ChangeScoringRules;
use Tournaments\Tournament\Application\Command\CreateTournament;
use Tournaments\Tournament\Application\Command\DiscardStage;
use Tournaments\Tournament\Application\Command\FinishTournament;
use Tournaments\Tournament\Application\Command\GenerateStage;
use Tournaments\Tournament\Application\Command\RenameTournament;
use Tournaments\Tournament\Application\Exception\NoStageGenerated;
use Tournaments\Tournament\Application\Exception\StageAlreadyGenerated;
use Tournaments\Tournament\Domain\Event\StageGenerated;
use Tournaments\Tournament\Domain\Event\TournamentCreated;
use Tournaments\Tournament\Domain\Event\TournamentReopened;
use Tournaments\Tournament\Domain\Event\TournamentStarted;
use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Exception\TournamentLocked;
use Tournaments\Tournament\Domain\Exception\TournamentNotFound;

final class TournamentLifecycleTest extends ApplicationTestCase
{
    #[Test]
    public function creates_tournament_reachable_by_both_links(): void
    {
        $created = ($this->createTournament)(new CreateTournament('Кубок', 'volleyball'));

        $byToken = $this->finder->byAdminToken($created->adminToken);
        $bySlug = $this->finder->bySlug($created->publicSlug);

        self::assertNotNull($byToken);
        self::assertSame($created->id, $byToken->id);
        self::assertSame($created->id, $bySlug?->id);
        self::assertSame('volleyball', $byToken->sport);
        self::assertSame(25, $byToken->rules['points_per_set']);
        self::assertSame('draft', $byToken->status);
        self::assertSame([], $byToken->participants);
        self::assertSame([], $byToken->stages);
        self::assertCount(1, $this->events->of(TournamentCreated::class));
    }

    #[Test]
    public function unknown_or_malformed_links_give_nothing(): void
    {
        self::assertNull($this->finder->byAdminToken('nope'));
        self::assertNull($this->finder->byAdminToken(str_repeat('a', 40)));
        self::assertNull($this->finder->bySlug('unknownslug'));

        $this->expectException(TournamentNotFound::class);
        $this->finder->byId('0199a4c0-0000-7000-8000-000000000000');
    }

    #[Test]
    public function custom_rules_on_creation_and_later(): void
    {
        $created = ($this->createTournament)(new CreateTournament('Короткий', 'table_tennis', [
            'type' => 'sets', 'sets_to_win' => 2, 'points_per_set' => 11, 'points_in_deciding_set' => 11,
        ]));
        self::assertSame(2, $this->view($created->id)->rules['sets_to_win']);

        ($this->changeScoringRules)(new ChangeScoringRules($created->id, ['type' => 'sets', 'sets_to_win' => 4, 'points_per_set' => 11, 'points_in_deciding_set' => 11]));
        ($this->renameTournament)(new RenameTournament($created->id, 'Длинный'));

        $view = $this->view($created->id);
        self::assertSame(4, $view->rules['sets_to_win']);
        self::assertSame('Длинный', $view->name);
    }

    #[Test]
    public function generating_stage_starts_tournament_and_saves_both_atomically(): void
    {
        $created = $this->createdTournament();
        $this->addPlayers($created->id, ['A', 'B', 'C']);

        $stageId = ($this->generateStage)(new GenerateStage($created->id, 'single_elimination', ['third_place_match' => true]));

        $view = $this->view($created->id);
        self::assertSame('in_progress', $view->status);
        self::assertCount(1, $view->stages);
        self::assertSame($stageId, $view->stages[0]->id);
        self::assertSame('single_elimination', $view->stages[0]->format);
        self::assertTrue($view->stages[0]->settings['third_place_match']);
        self::assertCount(2, $view->stages[0]->rounds);
        self::assertSame(1, $this->transaction->calls);
        self::assertCount(1, $this->events->of(TournamentStarted::class));
        self::assertCount(1, $this->events->of(StageGenerated::class));
    }

    #[Test]
    public function cannot_generate_without_participants_or_twice(): void
    {
        $created = $this->createdTournament();
        $this->addPlayers($created->id, ['Solo']);

        try {
            ($this->generateStage)(new GenerateStage($created->id, 'single_elimination'));
            self::fail('expected NotEnoughParticipants');
        } catch (NotEnoughParticipants) {
        }
        self::assertSame('draft', $this->view($created->id)->status);

        $this->addPlayers($created->id, ['Duo']);
        ($this->generateStage)(new GenerateStage($created->id, 'single_elimination'));

        $this->expectException(StageAlreadyGenerated::class);
        ($this->generateStage)(new GenerateStage($created->id, 'round_robin'));
    }

    #[Test]
    public function discarding_stage_returns_to_draft_and_unlocks_participants(): void
    {
        [$tournamentId] = $this->runningTournament(4);

        ($this->discardStage)(new DiscardStage($tournamentId));

        $view = $this->view($tournamentId);
        self::assertSame('draft', $view->status);
        self::assertSame([], $view->stages);
        self::assertCount(1, $this->events->of(TournamentReopened::class));

        $this->addPlayers($tournamentId, ['Опоздавший']);
        ($this->generateStage)(new GenerateStage($tournamentId, 'round_robin'));
        self::assertCount(5, $this->view($tournamentId)->participants);
        self::assertCount(5, $this->view($tournamentId)->stages[0]->rounds);
    }

    #[Test]
    public function cannot_discard_when_nothing_generated(): void
    {
        $created = $this->createdTournament();

        $this->expectException(NoStageGenerated::class);
        ($this->discardStage)(new DiscardStage($created->id));
    }

    #[Test]
    public function finishing_locks_tournament(): void
    {
        [$tournamentId] = $this->runningTournament(2);

        ($this->finishTournament)(new FinishTournament($tournamentId));

        self::assertSame('finished', $this->view($tournamentId)->status);
        $this->expectException(TournamentLocked::class);
        ($this->finishTournament)(new FinishTournament($tournamentId));
    }
}
