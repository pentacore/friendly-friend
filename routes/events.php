<?php

declare(strict_types=1);

use App\Http\Controllers\EventController;
use App\Http\Controllers\EventRsvpController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'guild.access'])->group(function (): void {
    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::get('events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::post('events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
    Route::put('events/{event}/rsvp', [EventRsvpController::class, 'update'])->name('events.rsvp');
});
