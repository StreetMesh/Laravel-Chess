<?php

use Illuminate\Support\Facades\Route;

/*
 * Both behind the door, because chess is something you do rather than something
 * you read — and doing anything here means arriving with a name issued
 * somewhere else first.
 */
Route::middleware('visitor')->group(function (): void {
    Route::livewire('chess', 'chess::lobby')->name('chess.lobby');

    Route::livewire('chess/{key}', 'chess::table')->name('chess.table');
});
