<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;
use Random\Randomizer;
use Tournaments\Shared\Application\Clock;
use Tournaments\Shared\Application\EventDispatcher;
use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Shared\Infrastructure\Laravel\DatabaseTransactionManager;
use Tournaments\Shared\Infrastructure\Laravel\LaravelEventDispatcher;
use Tournaments\Shared\Infrastructure\Laravel\SystemClock;
use Tournaments\Tournament\Domain\Stage\StageRepository;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;
use Tournaments\Tournament\Infrastructure\Persistence\PostgresStageRepository;
use Tournaments\Tournament\Infrastructure\Persistence\PostgresTournamentRepository;

/** Единственное место, где домен и приложение узнают о своих реализациях. */
final class TournamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(Randomizer::class, static fn () => new Randomizer);

        $this->app->bind(EventDispatcher::class, static fn ($app) => new LaravelEventDispatcher($app->make(Dispatcher::class)));
        $this->app->bind(TransactionManager::class, static fn ($app) => new DatabaseTransactionManager($app->make(ConnectionInterface::class)));
        $this->app->bind(TournamentRepository::class, static fn ($app) => new PostgresTournamentRepository($app->make(ConnectionInterface::class)));
        $this->app->bind(StageRepository::class, static fn ($app) => new PostgresStageRepository($app->make(ConnectionInterface::class)));
    }
}
