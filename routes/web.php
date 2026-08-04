<?php

use Illuminate\Support\Facades\Route;
use StreetMesh\Chess\Http\SettleController;

/*
 * Both behind the door, because chess is something you do rather than something
 * you read — and doing anything here means arriving with a name issued
 * somewhere else first.
 */
Route::middleware('visitor')->group(function (): void {
    Route::livewire('chess', 'chess::lobby')->name('chess.lobby');

    Route::livewire('chess/{key}', 'chess::table')->name('chess.table');

    /*
     * "The game is over, go and look."
     *
     * A browser can ask; it cannot say what happened. The answer comes from the
     * hub, and a game still being played answers nothing — so calling this
     * early, often, or for somebody else's table achieves nothing at all.
     */
    Route::post('chess/{key}/settle', SettleController::class)->name('chess.settle');
});
