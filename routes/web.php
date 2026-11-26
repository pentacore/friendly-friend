<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'guild.access'])->group(function (): void {
    Route::get('dashboard', fn (): Response => Inertia::render('Dashboard'))->name('dashboard');
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
