<?php

declare(strict_types=1);

use App\Access\AppSettings;
use App\Discord\DiscordClient;
use App\Discord\Testing\FakeDiscordClient;
use App\Enums\AppSetting;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\Guild;
use App\Models\Quote;
use App\Models\Setting;
use App\Models\User;
use App\Planning\Jobs\SendEventReminders;
use App\Quotes\Jobs\PruneExpiredDrafts;
use Illuminate\Support\Facades\Date;

function settingsOwner(): User
{
    config()->set('discord.owner_ids', ['4242']);

    return User::factory()->forDiscordUser(DiscordUser::factory()->create(['discord_id' => '4242']))->create();
}

/**
 * @return array<string, int>
 */
function defaultSettings(): array
{
    $values = [];

    foreach (AppSetting::cases() as $setting) {
        $values[$setting->value] = $setting->default();
    }

    return $values;
}

it('shows owners every setting with its value and default', function (): void {
    Setting::query()->create(['key' => AppSetting::TopListSize->value, 'value' => 15]);

    $this->actingAs(settingsOwner())->get(route('admin.settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Settings')
            ->has('settings', count(AppSetting::cases()))
            ->where('settings.'.array_search(AppSetting::TopListSize, AppSetting::cases(), true).'.value', 15)
            ->where('settings.'.array_search(AppSetting::TopListSize, AppSetting::cases(), true).'.default', 10));
});

it('keeps the settings page from server admins and members', function (): void {
    $admin = User::factory()->memberOf(Guild::factory()->create(['admin_role_ids' => ['900']]), ['900'])->create();

    $this->actingAs($admin)->get(route('admin.settings.index'))->assertForbidden();
    $this->actingAs($admin)->put(route('admin.settings.update'), defaultSettings())->assertForbidden();
});

it('saves the settings owners change', function (): void {
    $this->actingAs(settingsOwner())
        ->put(route('admin.settings.update'), [...defaultSettings(), AppSetting::ReminderFirstHours->value => 48])
        ->assertRedirect(route('admin.settings.index'));

    expect(resolve(AppSettings::class)->get(AppSetting::ReminderFirstHours))->toBe(48);
});

it('refuses values outside a setting\'s bounds', function (string $key, int $value): void {
    $this->actingAs(settingsOwner())
        ->put(route('admin.settings.update'), [...defaultSettings(), $key => $value])
        ->assertSessionHasErrors($key);
})->with([
    'a draft that outlives Discord\'s 15 minutes' => [AppSetting::DraftTtlMinutes->value, 15],
    'no reminder at all' => [AppSetting::ReminderSecondHours->value, 0],
    'an empty top list' => [AppSetting::TopListSize->value, 0],
]);

it('refuses a second reminder that comes before the first', function (): void {
    $this->actingAs(settingsOwner())
        ->put(route('admin.settings.update'), [...defaultSettings(), AppSetting::ReminderFirstHours->value => 3, AppSetting::ReminderSecondHours->value => 5])
        ->assertSessionHasErrors(AppSetting::ReminderSecondHours->value);
});

it('uses the default for a missing setting and clamps a stored one', function (): void {
    Setting::query()->create(['key' => AppSetting::DraftTtlMinutes->value, 'value' => 99]);
    $settings = resolve(AppSettings::class);

    expect($settings->get(AppSetting::TopListSize))->toBe(10)
        ->and($settings->get(AppSetting::DraftTtlMinutes))->toBe(14);
});

it('sends the first reminder as many hours ahead as the setting says', function (): void {
    Date::setTestNow('2026-11-27T12:00:00Z');
    config()->set('discord.bot_token', 'token');
    app()->instance(DiscordClient::class, $discord = new FakeDiscordClient);
    Setting::query()->create(['key' => AppSetting::ReminderFirstHours->value, 'value' => 48]);
    $guild = Guild::factory()->create(['discord_id' => '555']);
    $going = User::factory()->memberOf($guild)->create();
    $event = Event::factory()->create(['guild_id' => $guild->id, 'created_by_discord_user_id' => $going->discord_user_id, 'starts_at' => '2026-11-29T04:00:00Z']);
    $event->rsvps()->create(['discord_user_id' => $going->discord_user_id, 'status' => 'going']);

    dispatch_sync(new SendEventReminders);

    expect($discord->directMessages())->toHaveCount(1)
        ->and($event->fresh()?->reminded_day_at)->not->toBeNull();
});

it('expires drafts after the minutes the setting says', function (): void {
    Setting::query()->create(['key' => AppSetting::DraftTtlMinutes->value, 'value' => 5]);
    $old = Quote::factory()->draft()->create(['updated_at' => now()->subMinutes(6)]);
    $fresh = Quote::factory()->draft()->create(['updated_at' => now()->subMinutes(4)]);

    dispatch_sync(new PruneExpiredDrafts);

    expect(Quote::query()->find($old->id))->toBeNull()
        ->and(Quote::query()->find($fresh->id))->not->toBeNull();
});
