<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage\Format;

use Tournaments\Tournament\Domain\Exception\CannotPairRound;
use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;

/**
 * Жеребьёвка швейцарской системы, упрощённая голландская схема.
 *
 * Первый тур разводится по посеву: сильнейшая половина против слабейшей. Дальше участники
 * сортируются по очкам и спариваются сверху вниз так, чтобы никто не встретился дважды.
 * Когда соседей спарить нельзя, перебор отступает назад и пробует другого соперника.
 *
 * Это не строгая система FIDE: здесь нет плавающих групп очков и жёстких правил цвета.
 * Для любительского турнира этого достаточно, а результат предсказуем и объясним.
 */
final class SwissPairing
{
    /** Сколько туров имеет смысл играть: столько, чтобы выявился единоличный лидер. */
    public static function suggestedRounds(int $participants): int
    {
        return max(1, (int) ceil(log(max(2, $participants), 2)));
    }

    /**
     * @param  list<ParticipantId>  $seeded  участники в порядке посева
     * @param  list<Fixture>  $played  все матчи предыдущих туров
     * @return list<SwissPair>
     */
    public function pair(array $seeded, array $played, int $round): array
    {
        $table = $this->snapshot($seeded, $played);

        $queue = array_values($table);
        if ($round === 1) {
            usort($queue, static fn (SwissStandingSnapshot $a, SwissStandingSnapshot $b) => $a->seed <=> $b->seed);
        } else {
            usort($queue, static fn (SwissStandingSnapshot $a, SwissStandingSnapshot $b) => [$b->score, $a->seed] <=> [$a->score, $b->seed]);
        }

        $bye = null;
        if (count($queue) % 2 === 1) {
            $bye = $this->pickBye($queue);
            $queue = array_values(array_filter($queue, static fn (SwissStandingSnapshot $p) => $p !== $bye));
        }

        $pairs = $round === 1 ? $this->pairFirstRound($queue) : $this->pairByScore($queue);
        if ($pairs === null) {
            throw new CannotPairRound('Все возможные пары уже сыграны между собой: добавьте туры или закончите стадию');
        }

        $result = array_map(fn (array $pair) => $this->withSides($pair[0], $pair[1]), $pairs);
        if ($bye !== null) {
            $result[] = new SwissPair($bye->id);
        }

        return $result;
    }

    /**
     * Первый тур: верхняя половина играет с нижней, первый сеяный с первым из второй половины.
     *
     * @param  list<SwissStandingSnapshot>  $queue
     * @return list<array{0: SwissStandingSnapshot, 1: SwissStandingSnapshot}>
     */
    private function pairFirstRound(array $queue): array
    {
        $half = intdiv(count($queue), 2);
        $top = array_slice($queue, 0, $half);
        $bottom = array_slice($queue, $half);

        $pairs = [];
        foreach ($top as $index => $player) {
            $pairs[] = [$player, $bottom[$index]];
        }

        return $pairs;
    }

    /**
     * Перебор с возвратом: берём верхнего из очереди и ищем ему ближайшего соперника,
     * с которым он ещё не играл, так чтобы и остальных удалось разложить.
     *
     * @param  list<SwissStandingSnapshot>  $queue
     * @return list<array{0: SwissStandingSnapshot, 1: SwissStandingSnapshot}>|null
     */
    private function pairByScore(array $queue): ?array
    {
        if ($queue === []) {
            return [];
        }

        $first = array_shift($queue);
        foreach ($queue as $index => $candidate) {
            if ($first->hasPlayed($candidate->id)) {
                continue;
            }

            $rest = $queue;
            unset($rest[$index]);
            $tail = $this->pairByScore(array_values($rest));
            if ($tail !== null) {
                return [[$first, $candidate], ...$tail];
            }
        }

        return null;
    }

    /**
     * Пропуск тура достаётся тому, кто ниже всех и ещё не отдыхал.
     *
     * @param  list<SwissStandingSnapshot>  $queue
     */
    private function pickBye(array $queue): SwissStandingSnapshot
    {
        foreach (array_reverse($queue) as $player) {
            if (! $player->hadBye) {
                return $player;
            }
        }

        return $queue[count($queue) - 1];
    }

    /** Стороны раздаются тому, кто реже был первым: так счёт по площадкам остаётся ровным. */
    private function withSides(SwissStandingSnapshot $one, SwissStandingSnapshot $two): SwissPair
    {
        if ($one->homeGames === $two->homeGames) {
            return new SwissPair($one->id, $two->id);
        }

        return $one->homeGames < $two->homeGames
            ? new SwissPair($one->id, $two->id)
            : new SwissPair($two->id, $one->id);
    }

    /**
     * @param  list<ParticipantId>  $seeded
     * @param  list<Fixture>  $played
     * @return array<string, SwissStandingSnapshot>
     */
    private function snapshot(array $seeded, array $played): array
    {
        $table = [];
        foreach ($seeded as $index => $id) {
            $table[$id->value] = new SwissStandingSnapshot($id, $index + 1);
        }

        foreach ($played as $fixture) {
            $home = $fixture->home();
            $away = $fixture->away();

            if ($fixture->isBye()) {
                $lonely = $home ?? $away;
                if ($lonely !== null && isset($table[$lonely->value])) {
                    $table[$lonely->value]->hadBye = true;
                    $table[$lonely->value]->score += 2;
                }

                continue;
            }

            if ($home === null || $away === null || ! isset($table[$home->value], $table[$away->value])) {
                continue;
            }

            $table[$home->value]->homeGames++;
            $table[$home->value]->opponents[$away->value] = true;
            $table[$away->value]->opponents[$home->value] = true;

            $winner = $fixture->outcome()?->winner();
            match ($winner) {
                Side::Home => $table[$home->value]->score += 2,
                Side::Away => $table[$away->value]->score += 2,
                null => $fixture->isFinished()
                    ? [$table[$home->value]->score++, $table[$away->value]->score++]
                    : null,
            };
        }

        return $table;
    }
}
