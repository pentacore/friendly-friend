<?php

declare(strict_types=1);

use App\Access\Jobs\SyncGuildMembers;
use App\Models\DiscordMember;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    config()->set('discord.guild_id', '555');
    config()->set('discord.bot_token', 'bot-token');
});

/**
 * @param  list<string>  $roles
 * @return array<string, mixed>
 */
function syncPayload(string $id, array $roles = ['100']): array
{
    return [
        'user' => ['id' => $id, 'username' => "user{$id}", 'global_name' => null, 'avatar' => null],
        'nick' => null,
        'avatar' => null,
        'roles' => $roles,
    ];
}

it('upserts every member and marks missing ones as gone', function (): void {
    $stays = DiscordMember::factory()->create(['discord_id' => '1', 'role_ids' => []]);
    $left = DiscordMember::factory()->create(['discord_id' => '9']);

    Http::fake([
        'discord.com/api/v10/guilds/555/members*' => Http::response([syncPayload('1', ['100']), syncPayload('2')]),
    ]);

    dispatch_sync(new SyncGuildMembers);

    expect($stays->refresh()->role_ids)->toBe(['100'])
        ->and($stays->in_guild)->toBeTrue()
        ->and($left->refresh()->in_guild)->toBeFalse()
        ->and(DiscordMember::query()->where('discord_id', '2')->exists())->toBeTrue();
});

it('skips the sync without a bot token', function (): void {
    config()->set('discord.bot_token', '');
    Http::fake();
    Log::spy();

    dispatch_sync(new SyncGuildMembers);

    Http::assertNothingSent();
    Log::shouldHaveReceived('warning')->once();
});

it('does not mark everyone as gone when discord returns nothing', function (): void {
    $member = DiscordMember::factory()->create(['discord_id' => '1']);
    Http::fake(['discord.com/api/v10/guilds/555/members*' => Http::response([])]);

    dispatch_sync(new SyncGuildMembers);

    expect($member->refresh()->in_guild)->toBeTrue();
});

it('is scheduled every three hours', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->description, SyncGuildMembers::class));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 */3 * * *');
});

it('can be run by hand', function (): void {
    Http::fake(['discord.com/api/v10/guilds/555/members*' => Http::response([syncPayload('1')])]);

    $this->artisan('discord:sync-members')->assertSuccessful();

    expect(DiscordMember::query()->where('discord_id', '1')->exists())->toBeTrue();
});
