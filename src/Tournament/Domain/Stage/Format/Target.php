<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage\Format;

use Tournaments\Tournament\Domain\Scoring\Side;
use Tournaments\Tournament\Domain\Stage\BracketSide;

/**
 * Адрес внутри строящейся сетки: ветка, раунд, номер матча и сторона.
 *
 * @internal
 */
final readonly class Target
{
    public function __construct(
        public BracketSide $bracket,
        public int $round,
        public int $position,
        public Side $side,
    ) {}

    public function key(): string
    {
        return Node::keyOf($this->bracket, $this->round, $this->position);
    }

    public function pointsTo(Node $node): bool
    {
        return $this->bracket === $node->bracket && $this->round === $node->round && $this->position === $node->position;
    }
}
