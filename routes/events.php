<?php

declare(strict_types=1);

use App\Http\Controllers\EventController;
use App\Http\Controllers\EventFoodController;
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

    Route::scopeBindings()->prefix('events/{event}')->name('events.food.')->controller(EventFoodController::class)->group(function (): void {
        Route::post('bring', 'storeBringItem')->name('bring.store');
        Route::delete('bring/{bringItem}', 'destroyBringItem')->name('bring.destroy');
        Route::put('bring/{bringItem}/claim', 'claim')->name('bring.claim');
        Route::post('shopping', 'storeShoppingItem')->name('shopping.store');
        Route::put('shopping/{shoppingItem}/tick', 'tick')->name('shopping.tick');
        Route::delete('shopping/{shoppingItem}', 'destroyShoppingItem')->name('shopping.destroy');
        Route::post('meals', 'storeMeal')->name('meals.store');
        Route::put('meals/{meal}', 'updateMeal')->name('meals.update');
        Route::delete('meals/{meal}', 'destroyMeal')->name('meals.destroy');
        Route::put('meals/{meal}/cook', 'cook')->name('meals.cook');
    });
});
