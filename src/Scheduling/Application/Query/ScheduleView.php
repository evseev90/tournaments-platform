<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Application\Query;

final readonly class ScheduleView
{
    /**
     * @param  list<VenueView>  $venues
     * @param  list<AssignmentView>  $assignments
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public array $venues,
        public array $assignments,
        public array $settings,
        public bool $planned,
    ) {}

    public static function empty(string $startsAt): self
    {
        return new self([], [], [
            'starts_at' => $startsAt,
            'match_minutes' => 30,
            'break_minutes' => 0,
            'rest_minutes' => 10,
        ], false);
    }
}
