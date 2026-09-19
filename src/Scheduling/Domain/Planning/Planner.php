<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Domain\Planning;

use Tournaments\Scheduling\Domain\Assignment;
use Tournaments\Scheduling\Domain\ScheduleSettings;
use Tournaments\Scheduling\Domain\Venue;

interface Planner
{
    /**
     * @param  list<PlannableMatch>  $matches
     * @param  list<Venue>  $venues
     * @return list<Assignment>
     */
    public function plan(array $matches, array $venues, ScheduleSettings $settings): array;
}
