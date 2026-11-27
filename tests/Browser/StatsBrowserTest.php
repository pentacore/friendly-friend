<?php

declare(strict_types=1);

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\Person;
use App\Models\StatDailyActivity;
use App\Models\StatDailyVoice;
use App\Models\StatDailyVoicePair;
use App\Models\StatMessage;
use App\Models\StatReaction;
use App\Models\User;

/**
 * Renders /stats, a person's stats and the privacy settings in a real
 * browser, in light and dark mode, and opts out through the dialog.
 */
beforeEach(function (): void {
    $this->guild = Guild::factory()->create(['name' => 'Claesson Notify']);
    $this->user = User::factory()->memberOf($this->guild)->create();
    $members = collect(['Martin', 'Bo', 'Kim', 'Alex'])->map(fn (string $name): DiscordUser => DiscordUser::factory()->create(['global_name' => $name]));
    Person::factory()->create(['display_name' => 'Martin', 'slug' => 'martin', 'discord_user_id' => $members[0]->id]);

    $day = today(config()->string('stats.timezone'))->toDateString();

    foreach ($members as $index => $member) {
        foreach ([[19, 5], [20, 9], [21, 4], [12, 2]] as [$hourOfDay, $count]) {
            foreach (range(0, 6) as $daysAgo) {
                StatMessage::factory()->create([
                    'guild_id' => $this->guild->id,
                    'discord_user_id' => $member->id,
                    'hour' => now()->subDays($daysAgo)->setTime($hourOfDay, 0),
                    'count' => $count * (4 - $index) + $daysAgo,
                ]);
            }
        }

        StatDailyVoice::query()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $member->id, 'day' => $day, 'seconds' => 3600 * (4 - $index) + 1200]);
        StatReaction::factory()->create(['guild_id' => $this->guild->id, 'reactor_discord_user_id' => $member->id, 'target_discord_user_id' => $members[($index + 1) % 4]->id, 'emoji' => ['😂', '💀', '🔥', '👀'][$index], 'day' => $day, 'count' => 10 - $index]);
    }

    StatDailyVoicePair::query()->create(['guild_id' => $this->guild->id, 'first_discord_user_id' => $members[0]->id, 'second_discord_user_id' => $members[1]->id, 'day' => $day, 'seconds' => 9000]);
    StatDailyVoicePair::query()->create(['guild_id' => $this->guild->id, 'first_discord_user_id' => $members[0]->id, 'second_discord_user_id' => $members[2]->id, 'day' => $day, 'seconds' => 4000]);
    StatDailyActivity::query()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $members[0]->id, 'activity_name' => 'Deep Rock Galactic', 'day' => $day, 'seconds' => 14400]);
    StatDailyActivity::query()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $members[1]->id, 'activity_name' => 'Deep Rock Galactic', 'day' => $day, 'seconds' => 7200]);
    StatDailyActivity::query()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $members[2]->id, 'activity_name' => 'Balatro', 'day' => $day, 'seconds' => 5400]);
});

it('renders the stats page', function (bool $dark): void {
    $this->actingAs($this->user);

    $page = visit('/stats');
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee('When it\'s busy')
        ->assertSee('Time together')
        ->assertSee('Deep Rock Galactic')
        ->assertNoJavaScriptErrors();

    $page->script('document.fonts.ready');
    $page->screenshot(fullPage: true, filename: 'stats-index-'.($dark ? 'dark' : 'light'));
})->with(['light' => false, 'dark' => true]);

it('renders the stats page on a phone', function (): void {
    $this->actingAs($this->user);

    visit('/stats')
        ->resize(390, 844)
        ->assertSee('Most messages')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'stats-index-phone');
});

it('shows stats on a person page', function (bool $dark): void {
    $this->actingAs($this->user);

    $page = visit('/people/martin');
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee('Most time together with')
        ->assertSee('Deep Rock Galactic')
        ->assertNoJavaScriptErrors();

    $page->script('document.fonts.ready');
    $page->screenshot(filename: 'people-stats-'.($dark ? 'dark' : 'light'));
})->with(['light' => false, 'dark' => true]);

it('opts out on the privacy page', function (bool $dark): void {
    $this->actingAs($this->user);

    $page = visit('/settings/privacy');
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee('Stats are on for you')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'privacy-on-'.($dark ? 'dark' : 'light'))
        ->click('Turn off stats')
        ->assertSee('Turn off stats and delete yours?')
        ->screenshot(filename: 'privacy-confirm-'.($dark ? 'dark' : 'light'))
        ->click('Turn off and delete')
        ->assertSee('Stats are off for you')
        ->assertSee('Turn stats back on')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'privacy-off-'.($dark ? 'dark' : 'light'));

    expect($this->user->discordUser->fresh()?->tracking_opted_out_at)->not->toBeNull();
})->with(['light' => false, 'dark' => true]);
