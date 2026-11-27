<?php

declare(strict_types=1);

use App\Access\Jobs\MarkMemberLeft;
use App\Access\Jobs\UpsertGuildMember;
use App\Discord\Gateway\GatewayEventRouter;
use App\Discord\Jobs\MarkGuildLeft;
use App\Discord\Jobs\RefreshGuild;
use App\Discord\Jobs\RegisterGuild;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function gatewayFixture(string $type, array $overrides = []): array
{
    $payload = json_decode((string) file_get_contents(base_path("tests/Fixtures/gateway/{$type}.json")), true);

    return array_replace(is_array($payload) ? $payload : [], $overrides);
}

function routeEvent(string $type, array $payload): void
{
    app(GatewayEventRouter::class)->route($type, $payload);
}

beforeEach(function (): void {
    Queue::fake();
});

it('registers a guild and syncs members when it becomes available', function (): void {
    routeEvent('GUILD_CREATE', gatewayFixture('GUILD_CREATE'));

    Queue::assertPushed(RegisterGuild::class, fn (RegisterGuild $job): bool => $job->guild->discordId === '555'
        && $job->guild->name === 'Friends'
        && count($job->guild->roles) === 3
        && $job->syncMembers);
});

it('updates a guild without a member sync on GUILD_UPDATE', function (): void {
    routeEvent('GUILD_UPDATE', gatewayFixture('GUILD_CREATE', ['name' => 'Renamed']));

    Queue::assertPushed(RegisterGuild::class, fn (RegisterGuild $job): bool => $job->guild->name === 'Renamed' && ! $job->syncMembers);
});

it('ignores unavailable guilds during outages', function (): void {
    routeEvent('GUILD_CREATE', ['id' => '555', 'unavailable' => true]);
    routeEvent('GUILD_DELETE', ['id' => '555', 'unavailable' => true]);

    Queue::assertNothingPushed();
});

it('marks a guild as left when the bot is removed', function (): void {
    routeEvent('GUILD_DELETE', gatewayFixture('GUILD_DELETE'));

    Queue::assertPushed(MarkGuildLeft::class, fn (MarkGuildLeft $job): bool => $job->guildDiscordId === '555');
});

it('refreshes a guild when its roles change', function (string $type): void {
    routeEvent($type, gatewayFixture('GUILD_ROLE_UPDATE'));

    Queue::assertPushed(RefreshGuild::class, fn (RefreshGuild $job): bool => $job->guildDiscordId === '555');
})->with(['GUILD_ROLE_CREATE', 'GUILD_ROLE_UPDATE', 'GUILD_ROLE_DELETE']);

it('queues a member upsert for the event\'s guild', function (string $type): void {
    routeEvent($type, gatewayFixture($type, ['guild_id' => '777']));

    Queue::assertPushed(UpsertGuildMember::class, fn (UpsertGuildMember $job): bool => $job->guildDiscordId === '777'
        && $job->member->discordId === '42');
})->with(['GUILD_MEMBER_ADD', 'GUILD_MEMBER_UPDATE']);

it('queues marking a member as gone from that guild', function (): void {
    routeEvent('GUILD_MEMBER_REMOVE', gatewayFixture('GUILD_MEMBER_REMOVE'));

    Queue::assertPushed(MarkMemberLeft::class, fn (MarkMemberLeft $job): bool => $job->guildDiscordId === '555'
        && $job->userDiscordId === '42');
});

it('ignores event types it does not handle', function (): void {
    routeEvent('TYPING_START', ['guild_id' => '555']);

    Queue::assertNothingPushed();
});

it('drops malformed payloads without throwing', function (string $type, array $payload): void {
    Log::spy();

    routeEvent($type, $payload);

    Queue::assertNothingPushed();
    Log::shouldHaveReceived('warning')->once();
})->with([
    'member without user' => ['GUILD_MEMBER_ADD', ['guild_id' => '555', 'user' => 'nope']],
    'member without guild' => ['GUILD_MEMBER_ADD', ['user' => ['id' => '1', 'username' => 'a']]],
    'leave without user' => ['GUILD_MEMBER_REMOVE', ['guild_id' => '555']],
    'guild without name' => ['GUILD_CREATE', ['id' => '555']],
    'role without guild' => ['GUILD_ROLE_UPDATE', ['role' => []]],
]);
