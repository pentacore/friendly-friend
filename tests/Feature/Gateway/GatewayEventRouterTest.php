<?php

declare(strict_types=1);

use App\Access\Jobs\MarkMemberLeft;
use App\Access\Jobs\SyncGuildMembers;
use App\Access\Jobs\UpsertGuildMember;
use App\Discord\Gateway\GatewayEventRouter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/**
 * @return array<string, mixed>
 */
function gatewayFixture(string $type, array $overrides = []): array
{
    $payload = json_decode((string) file_get_contents(base_path("tests/Fixtures/gateway/{$type}.json")), true);

    return array_replace(is_array($payload) ? $payload : [], $overrides);
}

beforeEach(function (): void {
    config()->set('discord.guild_id', '555');
    Queue::fake();
});

it('queues a member upsert when someone joins', function (): void {
    app(GatewayEventRouter::class)->route('GUILD_MEMBER_ADD', gatewayFixture('GUILD_MEMBER_ADD'));

    Queue::assertPushed(UpsertGuildMember::class, fn (UpsertGuildMember $job): bool => $job->member->discordId === '42'
        && $job->member->roleIds === ['100']);
});

it('queues a member upsert when a member changes', function (): void {
    app(GatewayEventRouter::class)->route('GUILD_MEMBER_UPDATE', gatewayFixture('GUILD_MEMBER_UPDATE'));

    Queue::assertPushed(UpsertGuildMember::class, fn (UpsertGuildMember $job): bool => $job->member->nick === 'Marre'
        && $job->member->roleIds === ['100', '900']);
});

it('queues marking a member as gone when they leave', function (): void {
    app(GatewayEventRouter::class)->route('GUILD_MEMBER_REMOVE', gatewayFixture('GUILD_MEMBER_REMOVE'));

    Queue::assertPushed(MarkMemberLeft::class, fn (MarkMemberLeft $job): bool => $job->discordId === '42');
});

it('queues a full member sync when the guild becomes available', function (): void {
    app(GatewayEventRouter::class)->route('GUILD_CREATE', gatewayFixture('GUILD_CREATE'));

    Queue::assertPushed(SyncGuildMembers::class);
});

it('ignores events from other guilds', function (string $type): void {
    $payload = $type === 'GUILD_CREATE'
        ? gatewayFixture($type, ['id' => '999'])
        : gatewayFixture($type, ['guild_id' => '999']);

    app(GatewayEventRouter::class)->route($type, $payload);

    Queue::assertNothingPushed();
})->with(['GUILD_MEMBER_ADD', 'GUILD_MEMBER_UPDATE', 'GUILD_MEMBER_REMOVE', 'GUILD_CREATE']);

it('ignores event types it does not handle', function (): void {
    app(GatewayEventRouter::class)->route('TYPING_START', ['guild_id' => '555']);

    Queue::assertNothingPushed();
});

it('drops malformed payloads without throwing', function (): void {
    Log::spy();

    app(GatewayEventRouter::class)->route('GUILD_MEMBER_ADD', ['guild_id' => '555', 'user' => 'nope']);

    Queue::assertNothingPushed();
    Log::shouldHaveReceived('warning')->once();
});
