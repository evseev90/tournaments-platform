<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage\Format;

use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Stage\StandingRow;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;

/**
 * Посев плей-офф по итогам групп: сначала все победители групп по порядку групп,
 * затем все вторые места и так далее.
 *
 * Со стандартным посевом сетки это даёт перекрёстные пары: при двух группах
 * победитель A играет со вторым местом B, а не со своим соседом по группе.
 */
final class PlayoffSeeder
{
    /**
     * @param  list<list<StandingRow>>  $standings  таблицы групп, каждая уже отсортирована по месту
     * @return list<ParticipantId>
     */
    public function seed(array $standings, int $advancePerGroup): array
    {
        if ($advancePerGroup < 1) {
            throw new NotEnoughParticipants('At least one participant per group must advance');
        }

        foreach ($standings as $index => $group) {
            if (count($group) < $advancePerGroup) {
                throw new NotEnoughParticipants(sprintf('Group %d has fewer than %d participants', $index + 1, $advancePerGroup));
            }
        }

        $seeded = [];
        for ($place = 0; $place < $advancePerGroup; $place++) {
            foreach ($standings as $group) {
                $seeded[] = $group[$place]->participantId;
            }
        }

        if (count($seeded) < 2) {
            throw new NotEnoughParticipants('Playoff needs at least two participants');
        }

        return $seeded;
    }
}
