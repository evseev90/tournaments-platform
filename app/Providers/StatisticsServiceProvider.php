<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\KeepRosterInSync;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Tournaments\Statistics\Application\Query\Statistics;
use Tournaments\Statistics\Domain\Roster\CompetitorRepository;
use Tournaments\Statistics\Domain\Roster\TournamentRoster;
use Tournaments\Statistics\Infrastructure\Persistence\PostgresCompetitorRepository;
use Tournaments\Statistics\Infrastructure\Persistence\PostgresStatistics;
use Tournaments\Statistics\Infrastructure\Tournament\DatabaseTournamentRoster;
use Tournaments\Tournament\Domain\Event\ParticipantAdded;
use Tournaments\Tournament\Domain\Event\ParticipantRemoved;

final class StatisticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CompetitorRepository::class, static fn ($app) => new PostgresCompetitorRepository($app->make(ConnectionInterface::class)));
        $this->app->bind(TournamentRoster::class, static fn ($app) => new DatabaseTournamentRoster($app->make(ConnectionInterface::class)));
        $this->app->bind(Statistics::class, static fn ($app) => new PostgresStatistics($app->make(ConnectionInterface::class)));
    }

    public function boot(): void
    {
        Event::listen(ParticipantAdded::class, [KeepRosterInSync::class, 'onParticipantAdded']);
        Event::listen(ParticipantRemoved::class, [KeepRosterInSync::class, 'onParticipantRemoved']);
    }
}
