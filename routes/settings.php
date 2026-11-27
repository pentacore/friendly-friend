<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\PrivacyController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::middleware(['auth', 'guild.access'])->group(function (): void {
    Route::redirect('settings', '/settings/appearance');

    Route::get('settings/appearance', fn (): Response => Inertia::render('settings/Appearance'))->name('appearance.edit');

    Route::get('settings/privacy', [PrivacyController::class, 'edit'])->name('privacy.edit');
    Route::put('settings/privacy', [PrivacyController::class, 'update'])->name('privacy.update');
});
