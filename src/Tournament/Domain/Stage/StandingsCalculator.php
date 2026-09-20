<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage;

use Tournaments\Tournament\Domain\Scoring\SetBasedOutcome;
use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;

/**
 * Таблица круговой и швейцарской стадии.
 *
 * Порядок: очки, затем тайбрейки. В круговой это личные встречи внутри группы с равными очками,
 * соотношение партий и очков в партиях. В швейцарской перед ними идёт Бухгольц, сумма очков
 * соперников: он показывает, насколько тяжёлый путь прошёл участник.
 */
final class StandingsCalculator
{
    /**
     * @param  list<ParticipantId>  $participants  в порядке посева
     * @param  list<Fixture>  $fixtures
     * @return list<StandingRow>
     */
    public function calculate(array $participants, array $fixtures, bool $useBuchholz = false): array
    {
        /** @var array<string, StandingRow> $rows */
        $rows = [];
        foreach ($participants as $seed => $id) {
            $rows[$id->value] = new StandingRow($id, $seed + 1);
        }

        $played = array_filter($fixtures, static fn (Fixture $f) => $f->isFinished() && $f->home() !== null && $f->away() !== null);
        foreach ($played as $fixture) {
            $this->apply($rows, $fixture);
        }

        // Пропуск тура в швейцарской системе засчитывается как победа, играть было не с кем.
        foreach ($fixtures as $fixture) {
            $lonely = $fixture->isBye() ? ($fixture->home() ?? $fixture->away()) : null;
            $row = $lonely === null ? null : ($rows[$lonely->value] ?? null);
            if ($row !== null) {
                $row->played++;
                $row->wins++;
                $row->hadBye = true;
            }
        }

        if ($useBuchholz) {
            $this->applyBuchholz($rows, $played);
        }

        $sorted = array_values($rows);
        usort($sorted, static fn (StandingRow $a, StandingRow $b) => $b->score() <=> $a->score());

        $result = [];
        foreach ($this->groupByScore($sorted) as $group) {
            $headToHead = $this->headToHead($group, $played);
            usort($group, static fn (StandingRow $a, StandingRow $b) => [
                $useBuchholz ? $b->buchholz : 0,
                $headToHead[$b->participantId->value],
                $b->setRatio(),
                $b->pointRatio(),
                $a->seed,
            ] <=> [
                $useBuchholz ? $a->buchholz : 0,
                $headToHead[$a->participantId->value],
                $a->setRatio(),
                $a->pointRatio(),
                $b->seed,
            ]);
            array_push($result, ...$group);
        }

        foreach ($result as $index => $row) {
            $row->rank = $index + 1;
        }

        return $result;
    }

    /**
     * @param  array<string, StandingRow>  $rows
     * @param  array<int, Fixture>  $played
     */
    private function applyBuchholz(array $rows, array $played): void
    {
        foreach ($played as $fixture) {
            $home = $fixture->home();
            $away = $fixture->away();
            if ($home === null || $away === null || ! isset($rows[$home->value], $rows[$away->value])) {
                continue;
            }
            $rows[$home->value]->buchholz += $rows[$away->value]->score();
            $rows[$away->value]->buchholz += $rows[$home->value]->score();
        }
    }

    /** @param array<string, StandingRow> $rows */
    private function apply(array $rows, Fixture $fixture): void
    {
        $outcome = $fixture->outcome();
        $home = $fixture->home();
        $away = $fixture->away();
        if ($outcome === null || $home === null || $away === null) {
            return;
        }
        $homeRow = $rows[$home->value] ?? null;
        $awayRow = $rows[$away->value] ?? null;
        if ($homeRow === null || $awayRow === null) {
            return;
        }

        $homeRow->played++;
        $awayRow->played++;

        match ($outcome->winner()) {
            Side::Home => [$homeRow->wins++, $awayRow->losses++],
            Side::Away => [$awayRow->wins++, $homeRow->losses++],
            null => [$homeRow->draws++, $awayRow->draws++],
        };

        if ($outcome instanceof SetBasedOutcome) {
            $homeRow->setsWon += $outcome->setsWon(Side::Home);
            $homeRow->setsLost += $outcome->setsWon(Side::Away);
            $awayRow->setsWon += $outcome->setsWon(Side::Away);
            $awayRow->setsLost += $outcome->setsWon(Side::Home);
            $homeRow->pointsWon += $outcome->pointsWon(Side::Home);
            $homeRow->pointsLost += $outcome->pointsWon(Side::Away);
            $awayRow->pointsWon += $outcome->pointsWon(Side::Away);
            $awayRow->pointsLost += $outcome->pointsWon(Side::Home);
        }
    }

    /**
     * @param  list<StandingRow>  $sorted
     * @return list<list<StandingRow>>
     */
    private function groupByScore(array $sorted): array
    {
        $groups = [];
        foreach ($sorted as $row) {
            $groups[$row->score()][] = $row;
        }

        return array_values($groups);
    }

    /**
     * Очки в матчах только между участниками группы.
     *
     * @param  list<StandingRow>  $group
     * @param  array<int, Fixture>  $played
     * @return array<string, int>
     */
    private function headToHead(array $group, array $played): array
    {
        $ids = array_map(static fn (StandingRow $r) => $r->participantId->value, $group);
        $scores = array_fill_keys($ids, 0);
        if (count($group) < 2) {
            return $scores;
        }

        foreach ($played as $fixture) {
            $home = $fixture->home()?->value;
            $away = $fixture->away()?->value;
            if ($home === null || $away === null || ! isset($scores[$home], $scores[$away])) {
                continue;
            }
            match ($fixture->outcome()?->winner()) {
                Side::Home => $scores[$home] += 2,
                Side::Away => $scores[$away] += 2,
                null => [$scores[$home]++, $scores[$away]++],
            };
        }

        return $scores;
    }
}
