<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tournaments\Tournament\Application\Command\AddParticipants;
use Tournaments\Tournament\Application\Command\AddParticipantsHandler;
use Tournaments\Tournament\Application\Command\CreateTournament;
use Tournaments\Tournament\Application\Command\CreateTournamentHandler;
use Tournaments\Tournament\Application\Command\GenerateStage;
use Tournaments\Tournament\Application\Command\GenerateStageHandler;
use Tournaments\Tournament\Application\Command\RecordFixtureResult;
use Tournaments\Tournament\Application\Command\RecordFixtureResultHandler;
use Tournaments\Tournament\Application\Command\ShuffleParticipants;
use Tournaments\Tournament\Application\Command\ShuffleParticipantsHandler;
use Tournaments\Tournament\Application\Query\ParticipantView;
use Tournaments\Tournament\Application\Query\TournamentFinder;
use Tournaments\Tournament\Domain\Event\FixtureResultRecorded;
use Tournaments\Tournament\Domain\Event\StageGenerated;
use Tournaments\Tournament\Domain\Event\TournamentCreated;
use Tournaments\Tournament\Domain\Exception\InvalidOutcome;

/** Сквозной сценарий через контейнер Laravel и настоящую базу: всё связано правильно. */
final class TournamentFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function quick_tournament_from_creation_to_first_result(): void
    {
        Event::fake([TournamentCreated::class, StageGenerated::class, FixtureResultRecorded::class]);

        $created = $this->app->make(CreateTournamentHandler::class)(new CreateTournament('Пятничный', 'table_tennis'));
        $this->app->make(AddParticipantsHandler::class)(new AddParticipants($created->id, 'player', [
            ['name' => 'Аня'], ['name' => 'Борис'], ['name' => 'Вера'], ['name' => 'Глеб'], ['name' => 'Дима'],
        ]));
        $this->app->make(ShuffleParticipantsHandler::class)(new ShuffleParticipants($created->id));
        $stageId = $this->app->make(GenerateStageHandler::class)(new GenerateStage($created->id, 'single_elimination'));

        $finder = $this->app->make(TournamentFinder::class);
        $view = $finder->byAdminToken($created->adminToken);
        self::assertNotNull($view);
        self::assertSame('in_progress', $view->status);
        self::assertEqualsCanonicalizing(['Аня', 'Борис', 'Вера', 'Глеб', 'Дима'], array_map(static fn (ParticipantView $p) => $p->name, $view->participants));
        self::assertCount(3, $view->stages[0]->rounds);

        $ready = array_values(array_filter($view->stages[0]->rounds[0]->fixtures, static fn ($f) => $f->status === 'ready'));
        self::assertCount(1, $ready);

        $this->app->make(RecordFixtureResultHandler::class)(new RecordFixtureResult($created->id, $stageId, $ready[0]->id, [
            'type' => 'sets', 'sets' => [[11, 3], [11, 4], [11, 5]],
        ]));

        $public = $finder->bySlug($created->publicSlug);
        self::assertNotNull($public);
        $fixture = array_values(array_filter($public->stages[0]->rounds[0]->fixtures, static fn ($f) => $f->id === $ready[0]->id))[0];
        self::assertSame('finished', $fixture->status);
        self::assertSame($fixture->homeId, $fixture->winnerId);
        self::assertSame($fixture->homeId, $public->stages[0]->rounds[1]->fixtures[0]->awayId);

        Event::assertDispatched(TournamentCreated::class);
        Event::assertDispatched(StageGenerated::class);
        Event::assertDispatched(FixtureResultRecorded::class);
    }

    #[Test]
    public function failed_command_leaves_nothing_behind(): void
    {
        $created = $this->app->make(CreateTournamentHandler::class)(new CreateTournament('Пятничный', 'volleyball'));
        $this->app->make(AddParticipantsHandler::class)(new AddParticipants($created->id, 'team', [['name' => 'A'], ['name' => 'B']]));
        $stageId = $this->app->make(GenerateStageHandler::class)(new GenerateStage($created->id, 'single_elimination'));
        $fixtureId = $this->app->make(TournamentFinder::class)->byId($created->id)->stages[0]->rounds[0]->fixtures[0]->id;

        try {
            $this->app->make(RecordFixtureResultHandler::class)(new RecordFixtureResult($created->id, $stageId, $fixtureId, [
                'type' => 'sets', 'sets' => [[25, 24], [25, 20], [25, 20]],
            ]));
            self::fail('expected InvalidOutcome');
        } catch (InvalidOutcome) {
        }

        self::assertSame('ready', $this->app->make(TournamentFinder::class)->byId($created->id)->stages[0]->rounds[0]->fixtures[0]->status);
    }
}
