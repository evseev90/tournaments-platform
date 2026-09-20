<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage\Format;

use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Domain\Stage\BracketSide;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\FixtureId;
use Tournaments\Tournament\Domain\Stage\FixtureSlot;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;

/**
 * Двойная сетка: вылетаешь после второго поражения.
 *
 * Верхняя сетка — обычная олимпийка. Проигравший каждого её матча падает в нижнюю,
 * где встречается с теми, кто проиграл раньше. Победитель нижней доходит до гранд-финала
 * и там играет с тем, кто не проиграл ни разу.
 *
 * Нижняя сетка чередует два вида раундов: в «малом» играют между собой те, кто уже в нижней,
 * в «большом» к ним добавляются свежие проигравшие из верхней. Проигравшие большого раунда
 * подсаживаются в обратном порядке, иначе соперники по верхней сетке встретились бы сразу же.
 *
 * Гранд-финал один: переигровки при победе нижней сетки нет, об этом сказано в документации.
 */
final class DoubleElimination implements FormatGenerator
{
    public function minParticipants(): int
    {
        return 3;
    }

    public function generate(array $seeded, StageSettings $settings): array
    {
        $count = count($seeded);
        if ($count < $this->minParticipants()) {
            throw new NotEnoughParticipants('Double elimination needs at least 3 participants');
        }

        $size = SingleElimination::bracketSize($count);
        $winnerRounds = (int) log($size, 2);

        $nodes = $this->buildWinners($seeded, $size, $winnerRounds);
        $this->buildLosers($nodes, $size, $winnerRounds);
        $this->buildGrandFinal($nodes, $winnerRounds);
        $this->collapseEmptyLosersMatches($nodes);

        return $this->materialize($nodes, $winnerRounds);
    }

    /**
     * Верхняя сетка повторяет олимпийку, только проигравшие не исчезают, а получают адрес в нижней.
     *
     * @param  list<ParticipantId>  $seeded
     * @return array<string, Node>
     */
    private function buildWinners(array $seeded, int $size, int $winnerRounds): array
    {
        $nodes = [];
        $order = SingleElimination::seedOrder($size);

        for ($round = 1; $round <= $winnerRounds; $round++) {
            $inRound = (int) ($size / (2 ** $round));
            for ($position = 0; $position < $inRound; $position++) {
                $node = new Node(BracketSide::Winners, $round, $position);
                if ($round === 1) {
                    $node->home = $seeded[$order[$position * 2] - 1] ?? null;
                    $node->away = $seeded[$order[$position * 2 + 1] - 1] ?? null;
                    $node->alive = $node->home !== null && $node->away !== null;
                }
                $nodes[$node->key()] = $node;
            }
        }

        for ($round = 1; $round < $winnerRounds; $round++) {
            foreach (self::inRound($nodes, BracketSide::Winners, $round) as $node) {
                $node->winTo = new Target(BracketSide::Winners, $round + 1, intdiv($node->position, 2), $node->position % 2 === 0 ? Side::Home : Side::Away);
            }
        }

        return $nodes;
    }

    /** @param array<string, Node> $nodes */
    private function buildLosers(array &$nodes, int $size, int $winnerRounds): void
    {
        $losersRounds = 2 * $winnerRounds - 2;

        for ($round = 1; $round <= $losersRounds; $round++) {
            foreach (range(0, $this->losersRoundSize($size, $round) - 1) as $position) {
                $node = new Node(BracketSide::Losers, $round, $position);
                $nodes[$node->key()] = $node;
            }
        }

        // Победители нижней сетки идут дальше по ней же.
        for ($round = 1; $round < $losersRounds; $round++) {
            $nextIsMajor = $round % 2 === 1;
            foreach (self::inRound($nodes, BracketSide::Losers, $round) as $node) {
                // В большом раунде число матчей не меняется, в малом — делится пополам.
                $nextPosition = $nextIsMajor ? $node->position : intdiv($node->position, 2);
                $side = $nextIsMajor ? Side::Home : ($node->position % 2 === 0 ? Side::Home : Side::Away);
                $node->winTo = new Target(BracketSide::Losers, $round + 1, $nextPosition, $side);
            }
        }

        // Проигравшие первого раунда верхней сетки заполняют первый раунд нижней.
        foreach (self::inRound($nodes, BracketSide::Winners, 1) as $node) {
            $node->loseTo = new Target(BracketSide::Losers, 1, intdiv($node->position, 2), $node->position % 2 === 0 ? Side::Home : Side::Away);
        }

        // Проигравшие следующих раундов подсаживаются в большие раунды, в обратном порядке.
        for ($winnerRound = 2; $winnerRound <= $winnerRounds; $winnerRound++) {
            $losersRound = ($winnerRound - 1) * 2;
            $targets = self::inRound($nodes, BracketSide::Losers, $losersRound);
            $sources = self::inRound($nodes, BracketSide::Winners, $winnerRound);
            $last = count($targets) - 1;

            foreach ($sources as $index => $node) {
                $node->loseTo = new Target(BracketSide::Losers, $losersRound, $last - $index, Side::Away);
            }
        }
    }

    /** @param array<string, Node> $nodes */
    private function buildGrandFinal(array &$nodes, int $winnerRounds): void
    {
        $final = new Node(BracketSide::GrandFinal, 1, 0);
        $nodes[$final->key()] = $final;

        $winnersFinal = $nodes[Node::keyOf(BracketSide::Winners, $winnerRounds, 0)];
        $winnersFinal->winTo = new Target(BracketSide::GrandFinal, 1, 0, Side::Home);

        $losersFinal = $nodes[Node::keyOf(BracketSide::Losers, 2 * $winnerRounds - 2, 0)];
        $losersFinal->winTo = new Target(BracketSide::GrandFinal, 1, 0, Side::Away);
    }

    /**
     * Убирает матчи нижней сетки, в которые некому приходить: при неполной верхней сетке
     * часть проигравших просто не появляется. Матч с единственным участником не играется,
     * и его гость проходит дальше сразу.
     *
     * @param  array<string, Node>  $nodes
     */
    private function collapseEmptyLosersMatches(array &$nodes): void
    {
        $ordered = array_values($nodes);
        usort($ordered, static fn (Node $a, Node $b) => [$a->bracket->value, $a->round, $a->position] <=> [$b->bracket->value, $b->round, $b->position]);

        foreach ($ordered as $node) {
            if ($node->bracket !== BracketSide::Losers) {
                continue;
            }

            $feeders = $this->feedersOf($nodes, $node);
            if (count($feeders) >= 2) {
                $node->alive = true;

                continue;
            }

            $node->alive = false;
            $node->dropped = true;

            // Единственный источник, если он есть, получает адрес следующего матча.
            foreach ($feeders as [$source, $viaWin]) {
                if ($viaWin) {
                    $source->winTo = $node->winTo;
                } else {
                    $source->loseTo = $node->winTo;
                }
            }
        }

        $nodes = array_filter($nodes, static fn (Node $node) => ! $node->dropped);
    }

    /**
     * Живые источники матча: те матчи, чей победитель или проигравший реально сюда придёт.
     *
     * @param  array<string, Node>  $nodes
     * @return list<array{0: Node, 1: bool}>
     */
    private function feedersOf(array $nodes, Node $target): array
    {
        $feeders = [];
        foreach ($nodes as $node) {
            if ($node->dropped) {
                continue;
            }
            if ($node->winTo?->pointsTo($target) === true && $this->producesWinner($node)) {
                $feeders[] = [$node, true];
            }
            if ($node->loseTo?->pointsTo($target) === true && $this->producesLoser($node)) {
                $feeders[] = [$node, false];
            }
        }

        return $feeders;
    }

    private function producesWinner(Node $node): bool
    {
        // Верхняя сетка всегда кого-то выдаёт наверх; нижняя — только если матч вообще состоится.
        return $node->bracket !== BracketSide::Losers || $node->alive;
    }

    private function producesLoser(Node $node): bool
    {
        // Проход без игры проигравшего не даёт.
        return $node->round > 1 || $node->alive;
    }

    /**
     * @param  array<string, Node>  $nodes
     * @return list<Fixture>
     */
    private function materialize(array $nodes, int $winnerRounds): array
    {
        $ids = [];
        foreach ($nodes as $key => $node) {
            $ids[$key] = FixtureId::generate();
        }

        /** @var list<Fixture> $fixtures */
        $fixtures = [];
        /** @var array<string, Fixture> $byKey */
        $byKey = [];

        foreach ($nodes as $key => $node) {
            $fixture = new Fixture(
                id: $ids[$key],
                round: $this->displayRound($node, $winnerRounds),
                position: $node->position,
                home: $node->home,
                away: $node->away,
                nextWin: $this->slot($ids, $node->winTo),
                nextLose: $this->slot($ids, $node->loseTo),
                bracket: $node->bracket,
            );
            $fixtures[] = $fixture;
            $byKey[$key] = $fixture;
        }

        // Проходы первого раунда: участник сразу занимает место в следующем матче.
        foreach ($nodes as $key => $node) {
            if ($node->bracket !== BracketSide::Winners || $node->round !== 1 || $node->alive) {
                continue;
            }
            $fixture = $byKey[$key];
            $fixture->markBye();
            $advancing = $fixture->winner();
            if ($advancing !== null && $fixture->nextWin !== null) {
                $target = array_find($fixtures, static fn (Fixture $f) => $f->id->equals($fixture->nextWin->fixtureId));
                $target?->assign($fixture->nextWin->side, $advancing);
            }
        }

        return $fixtures;
    }

    /**
     * Раунды нумеруются сквозной лентой: сначала верхняя сетка, затем нижняя, затем гранд-финал.
     * Так их порядок понятен расписанию, которое про ветки ничего не знает.
     */
    private function displayRound(Node $node, int $winnerRounds): int
    {
        return match ($node->bracket) {
            BracketSide::Winners => $node->round,
            BracketSide::Losers => $winnerRounds + $node->round,
            BracketSide::GrandFinal => $winnerRounds + (2 * $winnerRounds - 2) + 1,
        };
    }

    /** @param array<string, FixtureId> $ids */
    private function slot(array $ids, ?Target $target): ?FixtureSlot
    {
        if ($target === null) {
            return null;
        }
        $id = $ids[$target->key()] ?? null;

        return $id === null ? null : new FixtureSlot($id, $target->side);
    }

    private function losersRoundSize(int $size, int $round): int
    {
        // Пары матчей: раунды 1 и 2 имеют size/4 матчей, 3 и 4 — size/8, и так далее.
        $step = intdiv($round + 1, 2);

        return max(1, (int) ($size / (2 ** ($step + 1))));
    }

    /**
     * @param  array<string, Node>  $nodes
     * @return list<Node>
     */
    private static function inRound(array $nodes, BracketSide $bracket, int $round): array
    {
        $found = array_values(array_filter(
            $nodes,
            static fn (Node $node) => $node->bracket === $bracket && $node->round === $round && ! $node->dropped,
        ));
        usort($found, static fn (Node $a, Node $b) => $a->position <=> $b->position);

        return $found;
    }
}
