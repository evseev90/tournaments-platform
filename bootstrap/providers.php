<?php

use App\Providers\AppServiceProvider;
use App\Providers\SchedulingServiceProvider;
use App\Providers\TournamentServiceProvider;

return [
    AppServiceProvider::class,
    TournamentServiceProvider::class,
    SchedulingServiceProvider::class,
];
