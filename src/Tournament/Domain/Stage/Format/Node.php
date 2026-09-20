<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage\Format;

use Tournaments\Tournament\Domain\Stage\BracketSide;
use Tournaments\Tournament\Domain\Tournament\ParticipantId;

/**
 * Черновик матча внутри генератора двойной сетки. Ссылки здесь ещё можно перекладывать,
 * поэтому граф сначала строится на черновиках и только потом превращается в матчи.
 *
 * @internal
 */
final class Node
{
    public ?ParticipantId $home = null;

    public ?ParticipantId $away = null;

    public ?Target $winTo = null;

    public ?Target $loseTo = null;

    /** Матч состоится: в нём будут оба участника. */
    public bool $alive = true;

    /** Матч выброшен из сетки: приходить в него некому. */
    public bool $dropped = false;

    public function __construct(
        public readonly BracketSide $bracket,
        public readonly int $round,
        public readonly int $position,
    ) {}

    public function key(): string
    {
        return self::keyOf($this->bracket, $this->round, $this->position);
    }

    public static function keyOf(BracketSide $bracket, int $round, int $position): string
    {
        return sprintf('%s-%d-%d', $bracket->value, $round, $position);
    }
}
