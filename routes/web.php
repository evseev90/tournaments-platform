<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ManageController;
use App\Http\Controllers\MyTournamentsController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\StatisticsController;
use App\Http\Middleware\ResolveOrganizerTournament;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'create'])->name('home');
Route::post('/tournaments', [HomeController::class, 'store'])->middleware('throttle:20,1')->name('tournaments.store');

Route::get('/t/{slug}', [PublicController::class, 'show'])->name('public.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/my', [MyTournamentsController::class, 'index'])->middleware('auth')->name('my.index');

Route::middleware('auth')->group(function (): void {
    Route::get('/players', [StatisticsController::class, 'index'])->name('players.index');
    Route::get('/players/{competitor}', [StatisticsController::class, 'show'])->name('players.show');
});

Route::prefix('/manage/{token}')->middleware(ResolveOrganizerTournament::class)->name('manage.')->group(function (): void {
    Route::get('/', [ManageController::class, 'show'])->name('show');
    Route::patch('/', [ManageController::class, 'rename'])->name('rename');
    Route::patch('/rules', [ManageController::class, 'rules'])->name('rules');
    Route::post('/finish', [ManageController::class, 'finish'])->name('finish');
    Route::post('/claim', [MyTournamentsController::class, 'claim'])->middleware('auth')->name('claim');

    Route::post('/participants', [ParticipantController::class, 'store'])->name('participants.store');
    Route::post('/participants/reorder', [ParticipantController::class, 'reorder'])->name('participants.reorder');
    Route::post('/participants/shuffle', [ParticipantController::class, 'shuffle'])->name('participants.shuffle');
    Route::patch('/participants/{participant}', [ParticipantController::class, 'update'])->name('participants.update');
    Route::delete('/participants/{participant}', [ParticipantController::class, 'destroy'])->name('participants.destroy');

    Route::post('/stage', [StageController::class, 'store'])->name('stage.store');
    Route::post('/groups', [StageController::class, 'storeGroups'])->name('groups.store');
    Route::post('/playoff', [StageController::class, 'storePlayoff'])->name('playoff.store');
    Route::post('/stages/{stage}/round', [StageController::class, 'pairRound'])->name('stage.round');
    Route::delete('/stage', [StageController::class, 'destroy'])->name('stage.destroy');

    Route::post('/stages/{stage}/fixtures/{fixture}/result', [ResultController::class, 'store'])->name('results.store');
    Route::delete('/stages/{stage}/fixtures/{fixture}/result', [ResultController::class, 'destroy'])->name('results.destroy');

    Route::post('/venues', [ScheduleController::class, 'storeVenue'])->name('venues.store');
    Route::patch('/venues/{venue}', [ScheduleController::class, 'updateVenue'])->name('venues.update');
    Route::delete('/venues/{venue}', [ScheduleController::class, 'destroyVenue'])->name('venues.destroy');
    Route::patch('/schedule', [ScheduleController::class, 'settings'])->name('schedule.settings');
    Route::post('/schedule', [ScheduleController::class, 'plan'])->name('schedule.plan');
    Route::delete('/schedule', [ScheduleController::class, 'clear'])->name('schedule.clear');
    Route::patch('/schedule/matches/{match}', [ScheduleController::class, 'move'])->name('schedule.move');
});
