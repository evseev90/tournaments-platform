<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage\Format;

use Tournaments\Tournament\Domain\Tournament\ParticipantId;

/** Пара очередного тура. Без соперника — это пропуск тура с автоматической победой. */
final readonly class SwissPair
{
    public function __construct(
        public ParticipantId $home,
        public ?ParticipantId $away = null,
    ) {}

    public function isBye(): bool
    {
        return $this->away === null;
    }
}
