<?php

declare(strict_types=1);

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\User;

/**
 * The owners' Servers page in a real browser, light and dark: roles,
 * channels for quotes and events, and watched channels.
 */
it('renders the Servers page and saves watched channels', function (bool $dark): void {
    config()->set('discord.owner_ids', ['4242']);
    $guild = Guild::factory()->create([
        'name' => 'Claesson Notify',
        'channels' => [['id' => '777', 'name' => 'quotes', 'position' => 1], ['id' => '778', 'name' => 'general', 'position' => 2], ['id' => '779', 'name' => 'events', 'position' => 3]],
    ]);
    $owner = User::factory()->forDiscordUser(DiscordUser::factory()->create(['discord_id' => '4242']))->memberOf($guild)->create();
    $this->actingAs($owner);

    $page = visit('/admin/guilds');
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee('Watch for posted quotes in')
        ->click('label:has-text("#quotes") button')
        ->press('Save')
        ->assertSee('Claesson Notify saved.')
        ->assertNoJavaScriptErrors();

    $page->script('document.fonts.ready');
    $page->screenshot(fullPage: true, filename: 'admin-guilds-'.($dark ? 'dark' : 'light'));

    expect($guild->fresh()?->watched_channel_ids)->toBe(['777']);
})->with(['light' => false, 'dark' => true]);
