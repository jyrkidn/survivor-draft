<?php

use App\Http\Controllers\CastController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\ScoreboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web
|--------------------------------------------------------------------------
|
| Authentication is Filament's, at /admin. There is no second login screen;
| these three screens sit behind the same `web` session guard Filament logs
| you into.
|
*/

Route::middleware(['auth'])->group(function (): void {
    Route::redirect('/', '/scoreboard')->name('home');

    Route::get('scoreboard', [ScoreboardController::class, 'show'])->name('scoreboard');
    Route::get('cast', [CastController::class, 'show'])->name('cast');

    Route::get('draft', [DraftController::class, 'show'])->name('draft');
    Route::post('draft/pick', [DraftController::class, 'pick'])->name('draft.pick');
    Route::post('draft/undo', [DraftController::class, 'undo'])->name('draft.undo');
});
