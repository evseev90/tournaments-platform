<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage\Format;

use Tournaments\Tournament\Domain\Stage\Fixture;
use Tournaments\Tournament\Domain\Stage\StageSettings;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;

interface FormatGenerator
{
    /**
     * Строит граф матчей. Участники приходят в порядке посева: первый — первый сеяный.
     *
     * @param  list<ParticipantId>  $seeded
     * @return list<Fixture>
     */
    public function generate(array $seeded, StageSettings $settings): array;

    public function minParticipants(): int;
}
