<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\CalendarSettingsController;
use App\Http\Controllers\Settings\FoodSettingsController;
use App\Http\Controllers\Settings\PrivacyController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::middleware(['auth', 'guild.access'])->group(function (): void {
    Route::redirect('settings', '/settings/appearance');

    Route::get('settings/appearance', fn (): Response => Inertia::render('settings/Appearance'))->name('appearance.edit');

    Route::get('settings/privacy', [PrivacyController::class, 'edit'])->name('privacy.edit');
    Route::put('settings/privacy', [PrivacyController::class, 'update'])->name('privacy.update');
    Route::get('settings/privacy/download', [PrivacyController::class, 'download'])->middleware('throttle:6,1')->name('privacy.download');

    Route::get('settings/food', [FoodSettingsController::class, 'edit'])->name('food-settings.edit');
    Route::put('settings/food', [FoodSettingsController::class, 'update'])->name('food-settings.update');

    Route::get('settings/calendar', [CalendarSettingsController::class, 'edit'])->name('calendar-settings.edit');
    Route::post('settings/calendar', [CalendarSettingsController::class, 'store'])->name('calendar-settings.store');
    Route::delete('settings/calendar', [CalendarSettingsController::class, 'destroy'])->name('calendar-settings.destroy');
});
