<?php

declare(strict_types=1);

namespace Tests\Domain\Scoring;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tournaments\Tournament\Domain\Exception\InvalidOutcome;
use Tournaments\Tournament\Domain\Scoring\PointBasedRules;
use Tournaments\Tournament\Domain\Scoring\PointOutcome;
use Tournaments\Tournament\Domain\Scoring\Side;

final class PointBasedRulesTest extends TestCase
{
    #[Test]
    public function higher_score_wins_and_equal_is_draw(): void
    {
        self::assertSame(Side::Home, (new PointOutcome(3, 1))->winner());
        self::assertSame(Side::Away, (new PointOutcome(0, 1))->winner());
        self::assertNull((new PointOutcome(2, 2))->winner());
        self::assertTrue((new PointOutcome(0, 0))->isDraw());
    }

    #[Test]
    public function draw_can_be_forbidden(): void
    {
        (new PointBasedRules(drawAllowed: true))->validate(new PointOutcome(1, 1));

        $this->expectException(InvalidOutcome::class);
        (new PointBasedRules(drawAllowed: false))->validate(new PointOutcome(1, 1));
    }
}
