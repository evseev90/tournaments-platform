<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ManageController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\StageController;
use App\Http\Middleware\ResolveOrganizerTournament;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'create'])->name('home');
Route::post('/tournaments', [HomeController::class, 'store'])->middleware('throttle:20,1')->name('tournaments.store');

Route::get('/t/{slug}', [PublicController::class, 'show'])->name('public.show');

Route::prefix('/manage/{token}')->middleware(ResolveOrganizerTournament::class)->name('manage.')->group(function (): void {
    Route::get('/', [ManageController::class, 'show'])->name('show');
    Route::patch('/', [ManageController::class, 'rename'])->name('rename');
    Route::patch('/rules', [ManageController::class, 'rules'])->name('rules');
    Route::post('/finish', [ManageController::class, 'finish'])->name('finish');

    Route::post('/participants', [ParticipantController::class, 'store'])->name('participants.store');
    Route::post('/participants/reorder', [ParticipantController::class, 'reorder'])->name('participants.reorder');
    Route::post('/participants/shuffle', [ParticipantController::class, 'shuffle'])->name('participants.shuffle');
    Route::patch('/participants/{participant}', [ParticipantController::class, 'update'])->name('participants.update');
    Route::delete('/participants/{participant}', [ParticipantController::class, 'destroy'])->name('participants.destroy');

    Route::post('/stage', [StageController::class, 'store'])->name('stage.store');
    Route::delete('/stage', [StageController::class, 'destroy'])->name('stage.destroy');

    Route::post('/stages/{stage}/fixtures/{fixture}/result', [ResultController::class, 'store'])->name('results.store');
    Route::delete('/stages/{stage}/fixtures/{fixture}/result', [ResultController::class, 'destroy'])->name('results.destroy');
});
