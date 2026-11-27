<?php

declare(strict_types=1);

use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'guild.access'])->group(function (): void {
    Route::get('stats', StatsController::class)->name('stats');
});
