<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Query;

final readonly class FixtureView
{
    /**
     * @param  array<string, mixed>|null  $outcome
     * @param  array{fixture_id: string, side: string}|null  $nextWin
     * @param  array{fixture_id: string, side: string}|null  $nextLose
     */
    public function __construct(
        public string $id,
        public int $round,
        public int $position,
        public ?string $homeId,
        public ?string $awayId,
        public string $status,
        public ?array $outcome,
        public ?string $winnerId,
        public ?array $nextWin,
        public ?array $nextLose,
    ) {}
}
