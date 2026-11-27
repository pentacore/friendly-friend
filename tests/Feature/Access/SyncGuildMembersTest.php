<?php

declare(strict_types=1);

use App\Access\Jobs\SyncAllGuilds;
use App\Access\Jobs\SyncGuildMembers;
use App\Discord\Data\GuildMemberData;
use App\Discord\DiscordClient;
use App\Discord\Testing\FakeDiscordClient;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\GuildMember;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

/**
 * @param  list<string>  $roles
 */
function syncMember(string $id, array $roles = ['100']): GuildMemberData
{
    return new GuildMemberData($id, "user{$id}", null, null, null, null, $roles);
}

function fakeDiscordWith(GuildMemberData ...$members): FakeDiscordClient
{
    $fake = new FakeDiscordClient()->withMembers(array_values($members));
    app()->instance(DiscordClient::class, $fake);

    return $fake;
}

it('records every member and marks missing ones as gone from that guild', function (): void {
    $guild = Guild::factory()->create(['discord_id' => '555']);
    $other = Guild::factory()->create(['discord_id' => '777']);
    $stays = GuildMember::factory()->for($guild)->for(DiscordUser::factory()->create(['discord_id' => '1']))->withRoles([])->create();
    $left = GuildMember::factory()->for($guild)->for(DiscordUser::factory()->create(['discord_id' => '9']))->create();
    $elsewhere = GuildMember::factory()->for($other)->for($left->discordUser)->create();
    fakeDiscordWith(syncMember('1'), syncMember('2'));

    dispatch_sync(new SyncGuildMembers('555'));

    expect($stays->refresh()->role_ids)->toBe(['100'])
        ->and($left->refresh()->in_guild)->toBeFalse()
        ->and($elsewhere->refresh()->in_guild)->toBeTrue()
        ->and($guild->members()->count())->toBe(3);
});

it('does nothing for disabled guilds', function (): void {
    Guild::factory()->disabled()->create(['discord_id' => '555']);
    fakeDiscordWith(syncMember('1'));

    dispatch_sync(new SyncGuildMembers('555'));

    expect(GuildMember::query()->count())->toBe(0);
});

it('does not mark everyone as gone when discord returns nothing', function (): void {
    $member = GuildMember::factory()->for(Guild::factory()->create(['discord_id' => '555']))->create();
    fakeDiscordWith();

    dispatch_sync(new SyncGuildMembers('555'));

    expect($member->refresh()->in_guild)->toBeTrue();
});

it('queues a sync for every enabled guild', function (): void {
    Queue::fake();
    Guild::factory()->create(['discord_id' => '1']);
    Guild::factory()->create(['discord_id' => '2']);
    Guild::factory()->disabled()->create(['discord_id' => '3']);

    new SyncAllGuilds()->handle();

    Queue::assertPushed(SyncGuildMembers::class, 2);
    Queue::assertNotPushed(SyncGuildMembers::class, fn (SyncGuildMembers $job): bool => $job->guildDiscordId === '3');
});

it('is scheduled every three hours', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->description, SyncAllGuilds::class));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 */3 * * *');
});

it('can be run by hand', function (): void {
    Guild::factory()->create(['discord_id' => '555']);
    fakeDiscordWith(syncMember('1'));

    $this->artisan('discord:sync-members')->assertSuccessful();

    expect(DiscordUser::query()->where('discord_id', '1')->exists())->toBeTrue();
});
