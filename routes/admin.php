<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\GuildController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'guild.access', 'can:manage-guilds'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('guilds', [GuildController::class, 'index'])->name('guilds.index');
        Route::put('guilds/{guild}', [GuildController::class, 'update'])->name('guilds.update');
    });
