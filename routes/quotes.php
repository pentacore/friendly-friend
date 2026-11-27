<?php

declare(strict_types=1);

use App\Http\Controllers\PersonController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteVoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'guild.access'])->group(function (): void {
    Route::get('quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('quotes/random', [QuoteController::class, 'random'])->name('quotes.random');
    Route::get('quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::put('quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
    Route::delete('quotes/{quote}', [QuoteController::class, 'destroy'])->name('quotes.destroy');
    Route::post('quotes/{quote}/vote', [QuoteVoteController::class, 'store'])->name('quotes.vote');
});

Route::middleware(['auth', 'guild.access'])->group(function (): void {
    Route::get('people/{person:slug}', [PersonController::class, 'show'])->name('people.show');
});
