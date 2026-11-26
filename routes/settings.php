<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::middleware(['auth', 'guild.access'])->group(function (): void {
    Route::redirect('settings', '/settings/appearance');

    Route::get('settings/appearance', fn (): Response => Inertia::render('settings/Appearance'))->name('appearance.edit');
});
