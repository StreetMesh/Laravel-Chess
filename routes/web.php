<?php

use Illuminate\Support\Facades\Route;
use StreetMesh\Chess\Http\SettleController;

/*
 * Both behind the door, because chess is something you do rather than something
 * you read — and doing anything here means arriving with a name issued
 * somewhere else first.
 */
Route::middleware('visitor')->group(function (): void {
    /*
     * Under the menu it is reached from. An experience is something you find at
     * a venue rather than a thing the venue is, and the address says so.
     */
    Route::livewire('experiences/chess', 'chess::lobby')->name('chess.lobby');

    Route::livewire('experiences/chess/{key}', 'chess::table')->name('chess.table');

    /*
     * "The game is over, go and look."
     *
     * A browser can ask; it cannot say what happened. The answer comes from the
     * hub, and a game still being played answers nothing — so calling this
     * early, often, or for somebody else's table achieves nothing at all.
     */
    Route::post('experiences/chess/{key}/settle', SettleController::class)->name('chess.settle');
});
