<?php

declare(strict_types=1);

namespace Tests\Domain\Stage;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tournaments\Tournament\Domain\Exception\CannotRevertResult;
use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Exception\RoundNotFinished;
use Tournaments\Tournament\Domain\Scoring\ScoringRules;
use Tournaments\Tournament\Domain\Scoring\SetBasedOutcome;
use Tournaments\Tournament\Domain\Scoring\SetBasedRules;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\Format\SwissPairing;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageFormat;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Stage\StandingRow;
use Tournaments\Tournament\Domain\Stage\StandingsCalculator;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\TournamentId;

final class SwissTest extends TestCase
{
    private DateTimeImmutable $now;

    private ScoringRules $rules;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-09-20 10:00:00');
        $this->rules = SetBasedRules::tableTennis();
    }

    /** @return iterable<string, array{int, int}> */
    public static function suggested(): iterable
    {
        yield '4 participants' => [4, 2];
        yield '8 participants' => [8, 3];
        yield '9 participants' => [9, 4];
        yield '16 participants' => [16, 4];
        yield '30 participants' => [30, 5];
    }

    #[Test]
    #[DataProvider('suggested')]
    public function number_of_rounds_is_suggested_by_field_size(int $participants, int $rounds): void
    {
        self::assertSame($rounds, SwissPairing::suggestedRounds($participants));
    }

    #[Test]
    public function needs_at_least_three_participants(): void
    {
        $this->expectException(NotEnoughParticipants::class);

        $this->stage(2);
    }

    #[Test]
    public function first_round_puts_the_top_half_against_the_bottom_half(): void
    {
        [$stage, $ids] = $this->stage(8);
        $first = $stage->fixturesOfRound(1);

        self::assertCount(4, $first);
        self::assertSame(1, $stage->roundsCount(), 'на старте есть только первый тур');
        self::assertSame(
            [[1, 5], [2, 6], [3, 7], [4, 8]],
            array_map(fn (Fixture $f) => [$this->seedOf($ids, $f->home()), $this->seedOf($ids, $f->away())], $first),
        );
    }

    #[Test]
    public function odd_field_gives_a_bye_to_the_lowest_seed(): void
    {
        [$stage, $ids] = $this->stage(5);
        $first = $stage->fixturesOfRound(1);

        self::assertCount(3, $first);
        $bye = array_values(array_filter($first, static fn (Fixture $f) => $f->isBye()));
        self::assertCount(1, $bye);
        self::assertSame(5, $this->seedOf($ids, $bye[0]->home()));
        self::assertNotNull($bye[0]->winner(), 'пропуск тура засчитывается как победа');
    }

    #[Test]
    public function next_round_waits_for_the_current_one(): void
    {
        [$stage] = $this->stage(8);

        try {
            $stage->pairNextRound(new SwissPairing, $this->now);
            self::fail('expected RoundNotFinished');
        } catch (RoundNotFinished) {
        }

        $this->playRound($stage, 1);
        $stage->pairNextRound(new SwissPairing, $this->now);

        self::assertSame(2, $stage->roundsCount());
        self::assertCount(4, $stage->fixturesOfRound(2));
    }

    #[Test]
    public function winners_meet_winners_in_the_second_round(): void
    {
        [$stage, $ids] = $this->stage(8);
        $this->playRound($stage, 1);
        $stage->pairNextRound(new SwissPairing, $this->now);

        // В первом туре побеждали верхние по посеву, значит во втором они играют между собой.
        $pairs = array_map(
            fn (Fixture $f) => [$this->seedOf($ids, $f->home()), $this->seedOf($ids, $f->away())],
            $stage->fixturesOfRound(2),
        );
        foreach ($pairs as [$home, $away]) {
            self::assertSame($home <= 4, $away <= 4, "посевы $home и $away имеют разное число побед");
        }
    }

    #[Test]
    public function nobody_meets_the_same_rival_twice(): void
    {
        [$stage] = $this->stage(8);

        $this->playWholeStage($stage);

        $seen = [];
        foreach ($stage->fixtures() as $fixture) {
            $home = $fixture->home();
            $away = $fixture->away();
            if ($home === null || $away === null) {
                continue;
            }
            $key = implode('|', [min($home->value, $away->value), max($home->value, $away->value)]);
            self::assertArrayNotHasKey($key, $seen, 'пара повторилась');
            $seen[$key] = true;
        }
    }

    #[Test]
    public function result_is_locked_once_the_next_round_is_paired(): void
    {
        [$stage] = $this->stage(8);
        $this->playRound($stage, 1);
        $first = $stage->fixturesOfRound(1)[0];

        // Пока следующий тур не разведён, ошибку в счёте ещё можно поправить.
        $stage->revertResult($first->id, $this->now);
        self::assertTrue($first->isReady());

        $this->playRound($stage, 1);
        $stage->pairNextRound(new SwissPairing, $this->now);

        $this->expectException(CannotRevertResult::class);
        $stage->revertResult($first->id, $this->now);
    }

    #[Test]
    public function result_of_the_current_round_can_still_be_corrected(): void
    {
        [$stage] = $this->stage(8);
        $this->playRound($stage, 1);
        $stage->pairNextRound(new SwissPairing, $this->now);
        $this->playRound($stage, 2);

        $second = $stage->fixturesOfRound(2)[0];
        $stage->revertResult($second->id, $this->now);

        self::assertTrue($second->isReady(), 'последний тур правится свободно');
        self::assertFalse($stage->awaitsNextRound());
    }

    #[Test]
    public function stage_finishes_only_after_the_planned_rounds(): void
    {
        [$stage] = $this->stage(8);
        self::assertSame(3, $stage->plannedRounds());

        $this->playRound($stage, 1);
        self::assertFalse($stage->isCompleted(), 'после первого тура стадия не окончена');
        self::assertTrue($stage->awaitsNextRound());

        $stage->pairNextRound(new SwissPairing, $this->now);
        $this->playRound($stage, 2);
        $stage->pairNextRound(new SwissPairing, $this->now);
        $this->playRound($stage, 3);

        self::assertTrue($stage->isCompleted());
        self::assertFalse($stage->awaitsNextRound());
        self::assertSame(12, count($stage->fixtures()), 'три тура по четыре матча');
    }

    #[Test]
    public function planned_rounds_can_be_set_explicitly(): void
    {
        [$stage] = $this->stage(8, new StageSettings(rounds: 5));

        self::assertSame(5, $stage->plannedRounds());
    }

    #[Test]
    public function byes_go_around_and_count_towards_the_table(): void
    {
        [$stage, $ids] = $this->stage(5);

        $this->playWholeStage($stage);

        $byesPerParticipant = [];
        foreach ($stage->fixtures() as $fixture) {
            if ($fixture->isBye()) {
                $lonely = $fixture->home();
                self::assertNotNull($lonely);
                $byesPerParticipant[$lonely->value] = ($byesPerParticipant[$lonely->value] ?? 0) + 1;
            }
        }
        self::assertSame([1, 1, 1], array_values($byesPerParticipant), 'пропуск тура не достаётся дважды');

        $table = (new StandingsCalculator)->calculate($ids, $stage->fixtures(), useBuchholz: true);
        foreach ($table as $row) {
            self::assertSame(3, $row->played, 'каждый сыграл три тура, считая пропуск');
        }
    }

    #[Test]
    public function buchholz_separates_participants_with_equal_scores(): void
    {
        [$stage, $ids] = $this->stage(8);
        $this->playWholeStage($stage);

        $table = (new StandingsCalculator)->calculate($ids, $stage->fixtures(), useBuchholz: true);

        $scores = array_map(static fn (StandingRow $row) => $row->score(), $table);
        $sorted = $scores;
        rsort($sorted);
        self::assertSame($sorted, $scores, 'таблица отсортирована по очкам');

        foreach ($table as $index => $row) {
            if ($index === 0) {
                continue;
            }
            $previous = $table[$index - 1];
            if ($previous->score() === $row->score()) {
                self::assertGreaterThanOrEqual($row->buchholz, $previous->buchholz, 'при равных очках выше тот, у кого Бухгольц больше');
            }
        }
        self::assertGreaterThan(0, $table[0]->buchholz);
    }

    /** Играет весь турнир: побеждает участник с меньшим номером посева. */
    private function playWholeStage(Stage $stage): void
    {
        $round = 1;
        while (true) {
            $this->playRound($stage, $round);
            if (! $stage->awaitsNextRound()) {
                break;
            }
            $stage->pairNextRound(new SwissPairing, $this->now);
            $round++;
        }
    }

    private function playRound(Stage $stage, int $round): void
    {
        foreach ($stage->fixturesOfRound($round) as $fixture) {
            if (! $fixture->isReady()) {
                continue;
            }
            $set = [11, 4];
            $stage->recordResult($fixture->id, SetBasedOutcome::of([$set, $set, $set]), $this->rules, $this->now);
        }
    }

    /** @param array<int, ParticipantId> $ids */
    private function seedOf(array $ids, ?ParticipantId $participant): ?int
    {
        foreach ($ids as $seed => $candidate) {
            if ($candidate->equals($participant)) {
                return $seed;
            }
        }

        return null;
    }

    /** @return array{Stage, array<int, ParticipantId>} */
    private function stage(int $count, ?StageSettings $settings = null): array
    {
        $ids = [];
        for ($seed = 1; $seed <= $count; $seed++) {
            $ids[$seed] = ParticipantId::generate();
        }
        $stage = Stage::generate(
            StageId::generate(),
            TournamentId::generate(),
            StageFormat::Swiss,
            $settings ?? StageSettings::default(),
            array_values($ids),
            $this->now,
        );

        return [$stage, $ids];
    }
}
