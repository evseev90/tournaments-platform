<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage\Format;

use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;

/**
 * Разводит участников по группам «змейкой»: первый сеяный в первую группу, второй во вторую,
 * а следующий круг идёт в обратную сторону. Так сильные не собираются в одной группе.
 *
 * Для четырёх групп порядок такой: A B C D, D C B A, A B C D, ...
 */
final class GroupSplitter
{
    public const int MAX_GROUPS = 16;

    /**
     * @param  list<ParticipantId>  $seeded  участники в порядке посева
     * @return list<list<ParticipantId>>
     */
    public function split(array $seeded, int $groups): array
    {
        if ($groups < 2 || $groups > self::MAX_GROUPS) {
            throw new NotEnoughParticipants(sprintf('Groups count must be between 2 and %d', self::MAX_GROUPS));
        }
        if (count($seeded) < $groups * 2) {
            throw new NotEnoughParticipants(sprintf('Need at least %d participants for %d groups', $groups * 2, $groups));
        }

        $result = array_fill(0, $groups, []);
        foreach ($seeded as $index => $participant) {
            $row = intdiv($index, $groups);
            $column = $index % $groups;
            $target = $row % 2 === 0 ? $column : $groups - 1 - $column;
            $result[$target][] = $participant;
        }

        return array_map(array_values(...), $result);
    }

    /** Имя группы по её номеру: 0 — «Группа A». */
    public static function name(int $index): string
    {
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'];

        return 'Группа '.($letters[$index] ?? (string) ($index + 1));
    }
}
