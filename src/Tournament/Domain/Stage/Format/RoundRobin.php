<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage\Format;

use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\FixtureId;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;

/**
 * Круговая система по методу кругового вращения (таблицы Бергера): каждый с каждым.
 * При нечётном числе участников один в каждом туре отдыхает, матч для него не создаётся.
 * Стороны раздаются жадно: при чётном числе участников «домашних» матчей у всех поровну с точностью до одного,
 * при нечётном разница может достигать двух.
 */
final class RoundRobin implements FormatGenerator
{
    public function minParticipants(): int
    {
        return 3;
    }

    public function generate(array $seeded, StageSettings $settings): array
    {
        if (count($seeded) < $this->minParticipants()) {
            throw new NotEnoughParticipants('Round robin needs at least 3 participants');
        }

        $firstLeg = $this->singleLeg($seeded);
        if ($settings->legs === 1) {
            return $firstLeg;
        }

        // Второй круг повторяет первый со сменой сторон.
        $roundsPerLeg = max([0, ...array_map(static fn (Fixture $f) => $f->round, $firstLeg)]);
        $secondLeg = array_map(
            static fn (Fixture $f) => new Fixture(FixtureId::generate(), $f->round + $roundsPerLeg, $f->position, $f->away(), $f->home()),
            $firstLeg,
        );

        return [...$firstLeg, ...$secondLeg];
    }

    /**
     * @param  list<ParticipantId>  $seeded
     * @return list<Fixture>
     */
    private function singleLeg(array $seeded): array
    {
        /** @var list<ParticipantId|null> $rotation */
        $rotation = $seeded;
        if (count($rotation) % 2 === 1) {
            $rotation[] = null;
        }
        $size = count($rotation);

        /** @var array<string, int> $homeGames */
        $homeGames = [];
        $fixtures = [];

        for ($round = 1; $round < $size; $round++) {
            $position = 0;
            for ($i = 0; $i < $size / 2; $i++) {
                $a = $rotation[$i];
                $b = $rotation[$size - 1 - $i];
                if ($a === null || $b === null) {
                    continue;
                }
                // Дома играет тот, у кого домашних матчей пока меньше; при равенстве чередуем.
                $homeA = $homeGames[$a->value] ?? 0;
                $homeB = $homeGames[$b->value] ?? 0;
                $swap = $homeA === $homeB ? ($round + $i) % 2 === 1 : $homeA > $homeB;
                [$home, $away] = $swap ? [$b, $a] : [$a, $b];
                $homeGames[$home->value] = ($homeGames[$home->value] ?? 0) + 1;

                $fixtures[] = new Fixture(FixtureId::generate(), $round, $position++, $home, $away);
            }
            // Первый стоит на месте, остальные сдвигаются по кругу.
            $last = array_pop($rotation);
            array_splice($rotation, 1, 0, [$last]);
        }

        return $fixtures;
    }
}
