<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\DiscordAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [DiscordAuthController::class, 'login'])->name('login');
    Route::get('auth/discord/redirect', [DiscordAuthController::class, 'redirect'])->name('auth.discord.redirect');
    Route::get('auth/discord/callback', [DiscordAuthController::class, 'callback'])->name('auth.discord.callback');
});

Route::post('logout', [DiscordAuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');
