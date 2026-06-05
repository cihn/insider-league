<?php

use App\Http\Controllers\MatchController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SimulationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'index'])->name('home');

Route::prefix('api')->name('api.')->group(function (): void {
    Route::get('state', [SimulationController::class, 'state'])->name('state');
    Route::post('fixtures', [SimulationController::class, 'generateFixtures'])->name('fixtures.generate');
    Route::post('simulate/next', [SimulationController::class, 'playNextWeek'])->name('simulate.next');
    Route::post('simulate/all', [SimulationController::class, 'playAllWeeks'])->name('simulate.all');
    Route::post('reset', [SimulationController::class, 'reset'])->name('reset');
    Route::patch('matches/{match}', [MatchController::class, 'update'])->name('matches.update');
});
