<?php

declare(strict_types=1);

namespace Tests\Application;

use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\Support\CollectingEventDispatcher;
use Tests\Support\FrozenClock;
use Tests\Support\ImmediateTransactionManager;
use Tests\Support\InMemoryStageRepository;
use Tests\Support\InMemoryTournamentRepository;
use Tournaments\Tournament\Application\Command\AddParticipants;
use Tournaments\Tournament\Application\Command\AddParticipantsHandler;
use Tournaments\Tournament\Application\Command\ChangeScoringRulesHandler;
use Tournaments\Tournament\Application\Command\CreatedTournament;
use Tournaments\Tournament\Application\Command\CreateTournament;
use Tournaments\Tournament\Application\Command\CreateTournamentHandler;
use Tournaments\Tournament\Application\Command\DiscardStageHandler;
use Tournaments\Tournament\Application\Command\FinishTournamentHandler;
use Tournaments\Tournament\Application\Command\GenerateStage;
use Tournaments\Tournament\Application\Command\GenerateStageHandler;
use Tournaments\Tournament\Application\Command\RecordFixtureResultHandler;
use Tournaments\Tournament\Application\Command\RemoveParticipantHandler;
use Tournaments\Tournament\Application\Command\RenameTournamentHandler;
use Tournaments\Tournament\Application\Command\ReorderParticipantsHandler;
use Tournaments\Tournament\Application\Command\RevertFixtureResultHandler;
use Tournaments\Tournament\Application\Command\ShuffleParticipantsHandler;
use Tournaments\Tournament\Application\Command\UpdateParticipantHandler;
use Tournaments\Tournament\Application\Query\TournamentFinder;
use Tournaments\Tournament\Application\Query\TournamentView;

/** Собирает все обработчики на in-memory реализациях портов. Ни базы, ни Laravel. */
abstract class ApplicationTestCase extends TestCase
{
    protected InMemoryTournamentRepository $tournaments;

    protected InMemoryStageRepository $stages;

    protected CollectingEventDispatcher $events;

    protected FrozenClock $clock;

    protected ImmediateTransactionManager $transaction;

    protected TournamentFinder $finder;

    protected CreateTournamentHandler $createTournament;

    protected RenameTournamentHandler $renameTournament;

    protected ChangeScoringRulesHandler $changeScoringRules;

    protected AddParticipantsHandler $addParticipants;

    protected UpdateParticipantHandler $updateParticipant;

    protected RemoveParticipantHandler $removeParticipant;

    protected ReorderParticipantsHandler $reorderParticipants;

    protected ShuffleParticipantsHandler $shuffleParticipants;

    protected GenerateStageHandler $generateStage;

    protected DiscardStageHandler $discardStage;

    protected RecordFixtureResultHandler $recordFixtureResult;

    protected RevertFixtureResultHandler $revertFixtureResult;

    protected FinishTournamentHandler $finishTournament;

    protected function setUp(): void
    {
        $this->tournaments = new InMemoryTournamentRepository;
        $this->stages = new InMemoryStageRepository;
        $this->events = new CollectingEventDispatcher;
        $this->clock = new FrozenClock;
        $this->transaction = new ImmediateTransactionManager;
        $this->finder = new TournamentFinder($this->tournaments, $this->stages);

        $this->createTournament = new CreateTournamentHandler($this->tournaments, $this->events, $this->clock);
        $this->renameTournament = new RenameTournamentHandler($this->tournaments, $this->transaction);
        $this->changeScoringRules = new ChangeScoringRulesHandler($this->tournaments, $this->transaction);
        $this->addParticipants = new AddParticipantsHandler($this->tournaments, $this->transaction, $this->events, $this->clock);
        $this->updateParticipant = new UpdateParticipantHandler($this->tournaments, $this->transaction);
        $this->removeParticipant = new RemoveParticipantHandler($this->tournaments, $this->transaction, $this->events, $this->clock);
        $this->reorderParticipants = new ReorderParticipantsHandler($this->tournaments, $this->transaction);
        $this->shuffleParticipants = new ShuffleParticipantsHandler($this->tournaments, $this->transaction, new Randomizer(new Mt19937(42)));
        $this->generateStage = new GenerateStageHandler($this->tournaments, $this->transaction, $this->events, $this->clock);
        $this->discardStage = new DiscardStageHandler($this->tournaments, $this->transaction, $this->events, $this->clock);
        $this->recordFixtureResult = new RecordFixtureResultHandler($this->tournaments, $this->transaction, $this->events, $this->clock);
        $this->revertFixtureResult = new RevertFixtureResultHandler($this->tournaments, $this->transaction, $this->events, $this->clock);
        $this->finishTournament = new FinishTournamentHandler($this->tournaments, $this->transaction, $this->events, $this->clock);
    }

    protected function createdTournament(string $sport = 'table_tennis', string $name = 'Клубный кубок'): CreatedTournament
    {
        return ($this->createTournament)(new CreateTournament($name, $sport));
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    protected function addPlayers(string $tournamentId, array $names, string $type = 'player'): array
    {
        return ($this->addParticipants)(new AddParticipants(
            $tournamentId,
            $type,
            array_map(static fn (string $n) => ['name' => $n], $names),
        ));
    }

    /** @return array{string, string, list<string>} турнир, стадия, участники по посеву */
    protected function runningTournament(int $players = 4, string $format = 'single_elimination'): array
    {
        $created = $this->createdTournament();
        $names = array_map(static fn (int $i) => "Игрок $i", range(1, $players));
        $ids = $this->addPlayers($created->id, $names);
        $stageId = ($this->generateStage)(new GenerateStage($created->id, $format));

        return [$created->id, $stageId, $ids];
    }

    protected function view(string $tournamentId): TournamentView
    {
        return $this->finder->byId($tournamentId);
    }
}
