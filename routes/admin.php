<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\GuildController;
use App\Http\Controllers\Admin\PeopleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'guild.access', 'can:manage-guilds'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('guilds', [GuildController::class, 'index'])->name('guilds.index');
        Route::put('guilds/{guild}', [GuildController::class, 'update'])->name('guilds.update');
    });

Route::middleware(['auth', 'guild.access', 'can:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('people', [PeopleController::class, 'index'])->name('people.index');
        Route::post('people/{person}/aliases', [PeopleController::class, 'storeAlias'])->name('people.aliases.store');
        Route::delete('people/{person}/aliases/{alias}', [PeopleController::class, 'destroyAlias'])->name('people.aliases.destroy');
        Route::put('people/{person}/link', [PeopleController::class, 'link'])->name('people.link');
        Route::post('people/{person}/merge', [PeopleController::class, 'merge'])->name('people.merge');
    });
