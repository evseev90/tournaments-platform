<?php

declare(strict_types=1);

namespace Tests\Domain\Stage;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Scoring\ScoringRules;
use Tournaments\Tournament\Domain\Scoring\SetBasedOutcome;
use Tournaments\Tournament\Domain\Scoring\SetBasedRules;
use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Domain\Stage\BracketSide;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\Stage;
use Tournaments\Tournament\Domain\Stage\StageFormat;
use Tournaments\Tournament\Domain\Stage\StageId;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;
use Tournaments\Tournament\Domain\Tournament\TournamentId;

final class DoubleEliminationTest extends TestCase
{
    private DateTimeImmutable $now;

    private ScoringRules $rules;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-09-20 10:00:00');
        $this->rules = SetBasedRules::tableTennis();
    }

    #[Test]
    public function needs_at_least_three_participants(): void
    {
        $this->expectException(NotEnoughParticipants::class);

        $this->stage(2);
    }

    #[Test]
    public function eight_participants_fill_both_brackets(): void
    {
        [$stage, $ids] = $this->stage(8);

        self::assertCount(14, $stage->fixtures(), 'семь матчей верхней сетки, шесть нижней и гранд-финал');
        self::assertCount(4, $this->of($stage, BracketSide::Winners, 1));
        self::assertCount(2, $this->of($stage, BracketSide::Winners, 2));
        self::assertCount(1, $this->of($stage, BracketSide::Winners, 3));
        self::assertCount(2, $this->of($stage, BracketSide::Losers, 1));
        self::assertCount(2, $this->of($stage, BracketSide::Losers, 2));
        self::assertCount(1, $this->of($stage, BracketSide::Losers, 3));
        self::assertCount(1, $this->of($stage, BracketSide::Losers, 4));
        self::assertCount(1, $this->bracket($stage, BracketSide::GrandFinal));

        $first = $this->of($stage, BracketSide::Winners, 1);
        self::assertTrue($first[0]->home()?->equals($ids[1]) ?? false, 'первый сеяный открывает верхнюю сетку');
        self::assertTrue($first[0]->isReady());
    }

    #[Test]
    public function losers_of_the_first_round_meet_in_the_lower_bracket(): void
    {
        [$stage] = $this->stage(8);
        $first = $this->of($stage, BracketSide::Winners, 1);
        $lower = $this->of($stage, BracketSide::Losers, 1);

        self::assertTrue($first[0]->nextLose?->fixtureId->equals($lower[0]->id) ?? false);
        self::assertTrue($first[1]->nextLose?->fixtureId->equals($lower[0]->id) ?? false);
        self::assertTrue($first[2]->nextLose?->fixtureId->equals($lower[1]->id) ?? false);
        self::assertSame(Side::Home, $first[0]->nextLose?->side);
        self::assertSame(Side::Away, $first[1]->nextLose?->side);
    }

    #[Test]
    public function fresh_losers_are_seated_in_reverse_so_rivals_do_not_meet_twice_in_a_row(): void
    {
        [$stage, $ids] = $this->stage(8);

        // Верхняя сетка: побеждает тот, кто выше по посеву.
        foreach ($this->of($stage, BracketSide::Winners, 1) as $fixture) {
            $this->win($stage, $fixture, Side::Home);
        }
        foreach ($this->of($stage, BracketSide::Losers, 1) as $fixture) {
            $this->win($stage, $fixture, Side::Home);
        }
        foreach ($this->of($stage, BracketSide::Winners, 2) as $fixture) {
            $this->win($stage, $fixture, Side::Home);
        }

        // Проигравший второго раунда верхней сетки не должен сразу встретить того,
        // кого сам выбил раундом раньше.
        foreach ($this->of($stage, BracketSide::Losers, 2) as $fixture) {
            self::assertTrue($fixture->isReady());
            $home = $fixture->home();
            $away = $fixture->away();
            self::assertNotNull($home);
            self::assertNotNull($away);
            self::assertFalse($this->alreadyPlayed($stage, $home, $away), 'повтор пары сразу после вылета');
        }
    }

    #[Test]
    public function loser_of_the_winners_final_drops_into_the_losers_final(): void
    {
        [$stage] = $this->stage(8);
        $winnersFinal = $this->of($stage, BracketSide::Winners, 3)[0];
        $losersFinal = $this->of($stage, BracketSide::Losers, 4)[0];
        $grandFinal = $this->bracket($stage, BracketSide::GrandFinal)[0];

        self::assertTrue($winnersFinal->nextLose?->fixtureId->equals($losersFinal->id) ?? false);
        self::assertTrue($winnersFinal->nextWin?->fixtureId->equals($grandFinal->id) ?? false);
        self::assertSame(Side::Home, $winnersFinal->nextWin?->side);
        self::assertTrue($losersFinal->nextWin?->fixtureId->equals($grandFinal->id) ?? false);
        self::assertSame(Side::Away, $losersFinal->nextWin?->side);
    }

    /** @return iterable<string, array{int, int}> */
    public static function sizes(): iterable
    {
        yield '3 participants' => [3, 4];
        yield '4 participants' => [4, 6];
        yield '5 participants' => [5, 8];
        yield '6 participants' => [6, 10];
        yield '7 participants' => [7, 12];
        yield '8 participants' => [8, 14];
        yield '11 participants' => [11, 20];
        yield '16 participants' => [16, 30];
    }

    #[Test]
    #[DataProvider('sizes')]
    public function everyone_leaves_after_two_defeats(int $count, int $expectedMatches): void
    {
        [$stage, $ids] = $this->stage($count);

        $played = $this->playEverything($stage);

        self::assertTrue($stage->isCompleted());
        self::assertSame($expectedMatches, $played, 'двойная сетка это 2N-2 матча');

        $defeats = [];
        foreach ($stage->fixtures() as $fixture) {
            $loser = $fixture->loser();
            if ($loser !== null) {
                $defeats[$loser->value] = ($defeats[$loser->value] ?? 0) + 1;
            }
        }

        $champion = $this->bracket($stage, BracketSide::GrandFinal)[0]->winner();
        self::assertNotNull($champion);
        self::assertLessThanOrEqual(1, $defeats[$champion->value] ?? 0, 'чемпион проигрывал не больше раза');

        foreach ($ids as $participant) {
            if ($participant->equals($champion)) {
                continue;
            }
            self::assertSame(2, $defeats[$participant->value] ?? 0, 'выбывший проигрывает ровно дважды');
        }
    }

    #[Test]
    public function byes_do_not_leave_empty_matches_in_the_lower_bracket(): void
    {
        foreach ([3, 5, 6, 7, 11, 13] as $count) {
            [$stage] = $this->stage($count);

            foreach ($stage->fixtures() as $fixture) {
                if ($fixture->bracket !== BracketSide::Losers) {
                    continue;
                }
                $sources = array_filter(
                    $stage->fixtures(),
                    static fn (Fixture $other) => $other->nextWin?->fixtureId->equals($fixture->id)
                        || $other->nextLose?->fixtureId->equals($fixture->id),
                );
                self::assertCount(2, $sources, "матч нижней сетки при $count участниках должен принимать двоих");
            }
        }
    }

    #[Test]
    public function rounds_are_numbered_as_one_line_for_scheduling(): void
    {
        [$stage] = $this->stage(8);

        $rounds = [];
        foreach ($stage->fixtures() as $fixture) {
            $rounds[$fixture->bracket?->value ?? 'none'][] = $fixture->round;
        }

        self::assertSame([1, 1, 1, 1, 2, 2, 3], $rounds['winners']);
        self::assertSame([4, 4, 5, 5, 6, 7], $rounds['losers']);
        self::assertSame([8], $rounds['grand_final']);
        self::assertSame(8, $stage->roundsCount());
    }

    /** Играет всё до конца, каждый раз побеждает сторона Home. */
    private function playEverything(Stage $stage): int
    {
        $played = 0;
        $guard = 0;
        do {
            $progress = false;
            foreach ($stage->fixtures() as $fixture) {
                if (! $fixture->isReady()) {
                    continue;
                }
                $this->win($stage, $fixture, Side::Home);
                $played++;
                $progress = true;
            }
            self::assertLessThan(100, ++$guard, 'сетка не сходится');
        } while ($progress);

        return $played;
    }

    private function win(Stage $stage, Fixture $fixture, Side $side): void
    {
        $set = $side === Side::Home ? [11, 4] : [4, 11];
        $stage->recordResult($fixture->id, SetBasedOutcome::of([$set, $set, $set]), $this->rules, $this->now);
    }

    private function alreadyPlayed(Stage $stage, ParticipantId $one, ParticipantId $two): bool
    {
        foreach ($stage->fixtures() as $fixture) {
            if (! $fixture->isFinished()) {
                continue;
            }
            if ($fixture->sideOf($one) !== null && $fixture->sideOf($two) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{Stage, array<int, ParticipantId>} стадия и участники по номеру посева
     */
    private function stage(int $count): array
    {
        $ids = [];
        for ($seed = 1; $seed <= $count; $seed++) {
            $ids[$seed] = ParticipantId::generate();
        }
        $stage = Stage::generate(
            StageId::generate(),
            TournamentId::generate(),
            StageFormat::DoubleElimination,
            StageSettings::default(),
            array_values($ids),
            $this->now,
        );

        return [$stage, $ids];
    }

    /** @return list<Fixture> */
    private function bracket(Stage $stage, BracketSide $side): array
    {
        return array_values(array_filter($stage->fixtures(), static fn (Fixture $f) => $f->bracket === $side));
    }

    /** @return list<Fixture> */
    private function of(Stage $stage, BracketSide $side, int $roundInBracket): array
    {
        $rounds = [];
        foreach ($this->bracket($stage, $side) as $fixture) {
            $rounds[$fixture->round][] = $fixture;
        }
        ksort($rounds);
        $list = array_values($rounds)[$roundInBracket - 1] ?? [];
        usort($list, static fn (Fixture $a, Fixture $b) => $a->position <=> $b->position);

        return $list;
    }
}
