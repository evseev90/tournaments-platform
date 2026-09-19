<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\KeepScheduleInSync;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Tournaments\Scheduling\Domain\Planning\GreedyPlanner;
use Tournaments\Scheduling\Domain\Planning\PlannableMatches;
use Tournaments\Scheduling\Domain\Planning\Planner;
use Tournaments\Scheduling\Domain\ScheduleRepository;
use Tournaments\Scheduling\Infrastructure\Persistence\PostgresScheduleRepository;
use Tournaments\Scheduling\Infrastructure\Tournament\TournamentPlannableMatches;
use Tournaments\Tournament\Domain\Event\FixtureResultRecorded;
use Tournaments\Tournament\Domain\Event\FixtureResultReverted;
use Tournaments\Tournament\Domain\Event\TournamentReopened;

final class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Planner::class, GreedyPlanner::class);
        $this->app->bind(PlannableMatches::class, TournamentPlannableMatches::class);
        $this->app->bind(ScheduleRepository::class, static fn ($app) => new PostgresScheduleRepository($app->make(ConnectionInterface::class)));
    }

    public function boot(): void
    {
        Event::listen(FixtureResultRecorded::class, [KeepScheduleInSync::class, 'onResultRecorded']);
        Event::listen(FixtureResultReverted::class, [KeepScheduleInSync::class, 'onResultReverted']);
        Event::listen(TournamentReopened::class, [KeepScheduleInSync::class, 'onTournamentReopened']);
    }
}
