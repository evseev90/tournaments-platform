<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage\Format;

use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\FixtureId;
use Tournaments\Tournament\Domain\Stage\FixtureSlot;
use Tournaments\Tournament\Domain\Stage\StageSettings;

/**
 * Олимпийская сетка. Размер сетки — ближайшая степень двойки, лишние места — bye у сильнейших сеяных.
 * Посев стандартный: первый против последнего, второй в противоположной половине.
 */
final class SingleElimination implements FormatGenerator
{
    public function minParticipants(): int
    {
        return 2;
    }

    public function generate(array $seeded, StageSettings $settings): array
    {
        $count = count($seeded);
        if ($count < $this->minParticipants()) {
            throw new NotEnoughParticipants('Single elimination needs at least 2 participants');
        }

        $size = self::bracketSize($count);
        $rounds = (int) log($size, 2);

        // Матчи по раундам: $fixtures[$round][$position]. Сначала создаём id, чтобы расставить ссылки.
        /** @var array<int, array<int, FixtureId>> $ids */
        $ids = [];
        for ($round = 1; $round <= $rounds; $round++) {
            $perRound = (int) ($size / (2 ** $round));
            for ($position = 0; $position < $perRound; $position++) {
                $ids[$round][$position] = FixtureId::generate();
            }
        }
        $thirdPlaceId = $settings->thirdPlaceMatch && $rounds >= 2 ? FixtureId::generate() : null;

        /** @var list<Fixture> $fixtures */
        $fixtures = [];
        /** @var array<string, Fixture> $byId */
        $byId = [];
        $order = self::seedOrder($size);

        for ($round = 1; $round <= $rounds; $round++) {
            foreach ($ids[$round] as $position => $id) {
                $isFinal = $round === $rounds;
                $isSemi = $round === $rounds - 1;

                $nextWin = $isFinal ? null : new FixtureSlot($ids[$round + 1][intdiv($position, 2)], $position % 2 === 0 ? Side::Home : Side::Away);
                $nextLose = $isSemi && $thirdPlaceId !== null
                    ? new FixtureSlot($thirdPlaceId, $position % 2 === 0 ? Side::Home : Side::Away)
                    : null;

                $home = $away = null;
                if ($round === 1) {
                    $home = $seeded[$order[$position * 2] - 1] ?? null;
                    $away = $seeded[$order[$position * 2 + 1] - 1] ?? null;
                }

                $fixture = new Fixture($id, $round, $position, $home, $away, nextWin: $nextWin, nextLose: $nextLose);
                $fixtures[] = $fixture;
                $byId[$id->value] = $fixture;
            }
        }

        if ($thirdPlaceId !== null) {
            $third = new Fixture($thirdPlaceId, $rounds, 1, null, null);
            $fixtures[] = $third;
            $byId[$thirdPlaceId->value] = $third;
        }

        // Bye: в первом раунде одна сторона пуста — участник проходит дальше без игры.
        foreach ($fixtures as $fixture) {
            if ($fixture->round !== 1 || ($fixture->home() !== null && $fixture->away() !== null)) {
                continue;
            }
            $fixture->markBye();
            $advancing = $fixture->winner();
            if ($advancing !== null && $fixture->nextWin !== null) {
                $byId[$fixture->nextWin->fixtureId->value]->assign($fixture->nextWin->side, $advancing);
            }
        }

        return $fixtures;
    }

    public static function bracketSize(int $participants): int
    {
        $size = 2;
        while ($size < $participants) {
            $size *= 2;
        }

        return $size;
    }

    /**
     * Порядок сеяных по позициям сетки: для 8 это [1, 8, 4, 5, 2, 7, 3, 6].
     *
     * @return list<int>
     */
    public static function seedOrder(int $size): array
    {
        $order = [1];
        while (count($order) < $size) {
            $next = [];
            $sum = count($order) * 2 + 1;
            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $sum - $seed;
            }
            $order = $next;
        }

        return $order;
    }
}
