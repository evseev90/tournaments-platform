<?php

declare(strict_types=1);

namespace Tests\Application;

use PHPUnit\Framework\Attributes\Test;
use Tournaments\Tournament\Application\Command\DiscardStage;
use Tournaments\Tournament\Application\Command\RecordFixtureResult;
use Tournaments\Tournament\Application\Command\RevertFixtureResult;
use Tournaments\Tournament\Application\Exception\StageHasResults;
use Tournaments\Tournament\Application\Query\FixtureView;
use Tournaments\Tournament\Domain\Event\FixtureResultRecorded;
use Tournaments\Tournament\Domain\Event\FixtureResultReverted;
use Tournaments\Tournament\Domain\Event\StageCompleted;
use Tournaments\Tournament\Domain\Exception\InvalidOutcome;

final class ResultsTest extends ApplicationTestCase
{
    #[Test]
    public function recorded_result_shows_up_in_view_and_advances_winner(): void
    {
        [$tournamentId, $stageId, $ids] = $this->runningTournament(4);
        $semi = $this->view($tournamentId)->stages[0]->rounds[0]->fixtures[0];
        self::assertSame([$ids[0], $ids[3]], [$semi->homeId, $semi->awayId]);

        ($this->recordFixtureResult)(new RecordFixtureResult($tournamentId, $stageId, $semi->id, [
            'type' => 'sets', 'sets' => [[11, 5], [9, 11], [11, 7], [11, 9]],
        ]));

        $stage = $this->view($tournamentId)->stages[0];
        $semi = $stage->rounds[0]->fixtures[0];
        $final = $stage->rounds[1]->fixtures[0];
        self::assertSame('finished', $semi->status);
        self::assertSame($ids[0], $semi->winnerId);
        self::assertSame([[11, 5], [9, 11], [11, 7], [11, 9]], $semi->outcome['sets'] ?? null);
        self::assertSame($ids[0], $final->homeId);
        self::assertNull($final->awayId);
        self::assertSame(['fixture_id' => $final->id, 'side' => 'home'], $semi->nextWin);
        self::assertCount(1, $this->events->of(FixtureResultRecorded::class));
    }

    #[Test]
    public function outcome_is_validated_against_tournament_rules(): void
    {
        [$tournamentId, $stageId] = $this->runningTournament(2);
        $final = $this->view($tournamentId)->stages[0]->rounds[0]->fixtures[0];

        $this->expectException(InvalidOutcome::class);
        ($this->recordFixtureResult)(new RecordFixtureResult($tournamentId, $stageId, $final->id, [
            'type' => 'sets', 'sets' => [[11, 5], [11, 5]],
        ]));
    }

    #[Test]
    public function walkover_and_revert(): void
    {
        [$tournamentId, $stageId] = $this->runningTournament(2);
        $final = $this->view($tournamentId)->stages[0]->rounds[0]->fixtures[0];

        ($this->recordFixtureResult)(new RecordFixtureResult($tournamentId, $stageId, $final->id, ['type' => 'walkover', 'winner' => 'away']));
        self::assertSame('completed', $this->view($tournamentId)->stages[0]->status);
        self::assertCount(1, $this->events->of(StageCompleted::class));

        ($this->revertFixtureResult)(new RevertFixtureResult($stageId, $final->id));

        $stage = $this->view($tournamentId)->stages[0];
        self::assertSame('in_progress', $stage->status);
        self::assertSame('ready', $stage->rounds[0]->fixtures[0]->status);
        self::assertNull($stage->rounds[0]->fixtures[0]->outcome);
        self::assertCount(1, $this->events->of(FixtureResultReverted::class));
    }

    #[Test]
    public function stage_with_results_cannot_be_discarded(): void
    {
        [$tournamentId, $stageId] = $this->runningTournament(2);
        $final = $this->view($tournamentId)->stages[0]->rounds[0]->fixtures[0];
        ($this->recordFixtureResult)(new RecordFixtureResult($tournamentId, $stageId, $final->id, ['type' => 'walkover', 'winner' => 'home']));

        $this->expectException(StageHasResults::class);
        ($this->discardStage)(new DiscardStage($tournamentId));
    }

    #[Test]
    public function round_robin_view_lists_every_round_ready(): void
    {
        [$tournamentId] = $this->runningTournament(5, 'round_robin');

        $stage = $this->view($tournamentId)->stages[0];
        self::assertSame('round_robin', $stage->format);
        self::assertCount(5, $stage->rounds);
        foreach ($stage->rounds as $round) {
            self::assertCount(2, $round->fixtures);
            self::assertSame(['ready', 'ready'], array_map(static fn (FixtureView $f) => $f->status, $round->fixtures));
            self::assertSame([null, null], array_map(static fn (FixtureView $f) => $f->nextWin, $round->fixtures));
        }
    }
}
