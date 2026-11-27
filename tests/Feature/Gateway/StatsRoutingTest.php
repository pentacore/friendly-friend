<?php

declare(strict_types=1);

use App\Discord\Gateway\GatewayEventRouter;
use App\Models\DiscordUser;
use App\Stats\Jobs\IngestMessage;
use App\Stats\Jobs\IngestPresence;
use App\Stats\Jobs\IngestReaction;
use App\Stats\Jobs\IngestVoiceState;
use App\Stats\Jobs\SeedGuildSessions;
use App\Stats\OptedOutMembers;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DiscordPayloads as P;

function routeStats(string $type, array $payload): void
{
    app(GatewayEventRouter::class)->route($type, $payload);
}

function optOutInCache(string $discordId): void
{
    DiscordUser::factory()->create(['discord_id' => $discordId])->forceFill(['tracking_opted_out_at' => now()])->save();
    app(OptedOutMembers::class)->refresh();
}

beforeEach(function (): void {
    Queue::fake();
});

it('counts guild chat messages without their content', function (): void {
    routeStats('MESSAGE_CREATE', P::message(extra: ['guild_id' => '555', 'type' => 0]));

    Queue::assertPushed(IngestMessage::class, fn (IngestMessage $job): bool => $job->guildDiscordId === '555'
        && $job->author->discordId === '50'
        && $job->channelId === '777'
        && $job->sentAt === '2026-11-20T21:15:00.000000+00:00');
});

it('does not count bots, webhooks, system messages or DMs', function (array $extra): void {
    routeStats('MESSAGE_CREATE', P::message(extra: $extra));

    Queue::assertNotPushed(IngestMessage::class);
})->with([
    'bot' => [['guild_id' => '555', 'author' => ['id' => '9', 'username' => 'b', 'bot' => true]]],
    'webhook' => [['guild_id' => '555', 'webhook_id' => '1']],
    'member joined notice' => [['guild_id' => '555', 'type' => 7]],
    'direct message' => [[]],
]);

it('drops every stats event from someone who opted out', function (): void {
    optOutInCache('42');

    routeStats('MESSAGE_CREATE', P::message(authorId: '42', extra: ['guild_id' => '555']));
    routeStats('MESSAGE_REACTION_ADD', P::reaction('42'));
    routeStats('VOICE_STATE_UPDATE', P::voiceState('42'));
    routeStats('PRESENCE_UPDATE', P::presence('42'));

    Queue::assertNothingPushed();
});

it('queues reactions with their target and emoji', function (array $emoji, string $expected): void {
    routeStats('MESSAGE_REACTION_ADD', P::reaction(emoji: $emoji));

    Queue::assertPushed(IngestReaction::class, fn (IngestReaction $job): bool => $job->reactorDiscordId === '42'
        && $job->targetDiscordId === '50'
        && $job->emoji === $expected);
})->with([
    'unicode' => [['id' => null, 'name' => '😂'], '😂'],
    'custom' => [['id' => '123', 'name' => 'kekw'], '123:kekw'],
]);

it('ignores reactions by bots', function (): void {
    routeStats('MESSAGE_REACTION_ADD', P::reaction(extra: ['member' => ['user' => ['id' => '42', 'username' => 'b', 'bot' => true]]]));

    Queue::assertNothingPushed();
});

it('queues voice joins and leaves, but not bots', function (): void {
    routeStats('VOICE_STATE_UPDATE', P::voiceState());
    routeStats('VOICE_STATE_UPDATE', P::voiceState(channelId: null));
    routeStats('VOICE_STATE_UPDATE', P::voiceState('9', bot: true));

    Queue::assertPushed(IngestVoiceState::class, 2);
    Queue::assertPushed(IngestVoiceState::class, fn (IngestVoiceState $job): bool => $job->channelId === null);
});

it('keeps only "Playing" activities, and nothing when offline', function (): void {
    routeStats('PRESENCE_UPDATE', P::presence(activities: [
        ['name' => 'Deep Rock Galactic', 'type' => 0],
        ['name' => 'Spotify', 'type' => 2],
        ['name' => 'Custom Status', 'type' => 4],
    ]));
    routeStats('PRESENCE_UPDATE', P::presence('43', status: 'offline'));

    Queue::assertPushed(IngestPresence::class, fn (IngestPresence $job): bool => $job->memberDiscordId === '42' && $job->playing === ['Deep Rock Galactic' => 0]);
    Queue::assertPushed(IngestPresence::class, fn (IngestPresence $job): bool => $job->memberDiscordId === '43' && $job->playing === []);
});

it('seeds sessions from the voice states and games in GUILD_CREATE', function (): void {
    optOutInCache('44');

    routeStats('GUILD_CREATE', P::fixture('GUILD_CREATE', [
        'voice_states' => [
            ['user_id' => '42', 'channel_id' => '700', 'member' => ['user' => ['id' => '42', 'username' => 'a']]],
            ['user_id' => '9', 'channel_id' => '700', 'member' => ['user' => ['id' => '9', 'username' => 'bot', 'bot' => true]]],
            ['user_id' => '44', 'channel_id' => '700'],
        ],
        'presences' => [
            P::presence('43'),
            P::presence('44'),
            P::presence('45', activities: [['name' => 'Spotify', 'type' => 2]]),
        ],
    ]));

    Queue::assertPushed(SeedGuildSessions::class, fn (SeedGuildSessions $job): bool => $job->guildDiscordId === '555'
        && $job->voice === ['42' => '700']
        && $job->playing === ['43' => ['Deep Rock Galactic' => 0]]);
});

it('does not reseed on GUILD_UPDATE', function (): void {
    routeStats('GUILD_UPDATE', P::fixture('GUILD_CREATE'));

    Queue::assertNotPushed(SeedGuildSessions::class);
});
