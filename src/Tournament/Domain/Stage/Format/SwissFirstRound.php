<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage\Format;

use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\FixtureId;
use Tournaments\Tournament\Domain\Stage\StageSettings;

/**
 * Швейцарская система создаётся по одному туру: заранее известен только первый,
 * дальше пары зависят от результатов. Поэтому генератор отвечает лишь за старт.
 */
final class SwissFirstRound implements FormatGenerator
{
    public function __construct(
        private readonly SwissPairing $pairing = new SwissPairing,
    ) {}

    public function minParticipants(): int
    {
        return 3;
    }

    public function generate(array $seeded, StageSettings $settings): array
    {
        if (count($seeded) < $this->minParticipants()) {
            throw new NotEnoughParticipants('Swiss system needs at least 3 participants');
        }

        return self::materialize($this->pairing->pair($seeded, [], 1), 1);
    }

    /**
     * @param  list<SwissPair>  $pairs
     * @return list<Fixture>
     */
    public static function materialize(array $pairs, int $round): array
    {
        $fixtures = [];
        foreach ($pairs as $position => $pair) {
            $fixture = new Fixture(FixtureId::generate(), $round, $position, $pair->home, $pair->away);
            if ($pair->isBye()) {
                $fixture->markBye();
            }
            $fixtures[] = $fixture;
        }

        return $fixtures;
    }
}
