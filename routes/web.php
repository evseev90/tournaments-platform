<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home', [
    'laravel' => app()->version(),
    'php' => PHP_VERSION,
]));
