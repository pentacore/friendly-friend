<?php

declare(strict_types=1);

use App\Access\Jobs\SyncGuildMembers;
use App\Discord\Data\GuildData;
use App\Discord\Data\GuildRoleData;
use App\Discord\DiscordClient;
use App\Discord\Jobs\MarkGuildLeft;
use App\Discord\Jobs\RefreshGuild;
use App\Discord\Jobs\RegisterGuild;
use App\Discord\Testing\FakeDiscordClient;
use App\Models\Guild;
use Illuminate\Support\Facades\Queue;

function guildData(string $name = 'Friends'): GuildData
{
    return new GuildData('555', $name, 'iconhash', [
        new GuildRoleData('100', 'Friends', 3447003, 1, false),
    ]);
}

it('registers a new guild as disabled without syncing members', function (): void {
    Queue::fake();

    new RegisterGuild(guildData())->handle();

    $guild = Guild::query()->sole();
    expect($guild->discord_id)->toBe('555')
        ->and($guild->name)->toBe('Friends')
        ->and($guild->enabled)->toBeFalse()
        ->and($guild->bot_present)->toBeTrue()
        ->and($guild->roles)->toEqual([['id' => '100', 'name' => 'Friends', 'color' => 3447003, 'position' => 1, 'managed' => false]])
        ->and($guild->iconUrl())->toBe('https://cdn.discordapp.com/icons/555/iconhash.png');
    Queue::assertNotPushed(SyncGuildMembers::class);
});

it('updates an enabled guild, keeps its settings and syncs members', function (): void {
    Queue::fake();
    Guild::factory()->create(['discord_id' => '555', 'name' => 'Old', 'bot_present' => false, 'allowed_role_ids' => ['100']]);

    new RegisterGuild(guildData('New'))->handle();

    $guild = Guild::query()->sole();
    expect($guild->name)->toBe('New')
        ->and($guild->bot_present)->toBeTrue()
        ->and($guild->enabled)->toBeTrue()
        ->and($guild->allowed_role_ids)->toBe(['100']);
    Queue::assertPushed(SyncGuildMembers::class, fn (SyncGuildMembers $job): bool => $job->guildDiscordId === '555');
});

it('refreshes a known guild\'s roles over REST', function (): void {
    Guild::factory()->create(['discord_id' => '555', 'roles' => []]);
    app()->instance(DiscordClient::class, new FakeDiscordClient()->withGuild(guildData()));

    dispatch_sync(new RefreshGuild('555'));

    expect(Guild::query()->sole()->roles)->toHaveCount(1);
});

it('marks a guild as left by the bot', function (): void {
    $guild = Guild::factory()->create(['discord_id' => '555']);

    dispatch_sync(new MarkGuildLeft('555'));

    expect($guild->refresh()->bot_present)->toBeFalse()
        ->and($guild->enabled)->toBeTrue();
});
