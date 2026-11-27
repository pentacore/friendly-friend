<?php

declare(strict_types=1);

use App\Discord\Gateway\BotHeartbeat;
use App\Discord\Gateway\GatewayIntents;
use Discord\WebSockets\Intents;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

it('is online right after a heartbeat', function (): void {
    $heartbeat = app(BotHeartbeat::class);

    expect($heartbeat->isOnline())->toBeFalse()
        ->and($heartbeat->lastBeatAt())->toBeNull();

    $heartbeat->beat();

    expect($heartbeat->isOnline())->toBeTrue()
        ->and($heartbeat->lastBeatAt()?->timestamp)->toBe(Date::now()->timestamp);
});

it('reads the heartbeat back from redis, which returns numbers as strings', function (): void {
    config()->set('cache.default', 'redis');
    Cache::store('redis')->forget('discord:bot:heartbeat');

    app(BotHeartbeat::class)->beat();

    expect(app(BotHeartbeat::class)->isOnline())->toBeTrue();
});

it('goes offline when the heartbeat is stale', function (): void {
    $heartbeat = app(BotHeartbeat::class);
    $heartbeat->beat();

    $this->travel(91)->seconds();

    expect($heartbeat->isOnline())->toBeFalse();
});

it('requests exactly the intents the spec needs', function (): void {
    expect(GatewayIntents::value())->toBe(
        Intents::GUILDS
        | Intents::GUILD_MEMBERS
        | Intents::GUILD_MESSAGES
        | Intents::MESSAGE_CONTENT
        | Intents::GUILD_MESSAGE_REACTIONS
        | Intents::GUILD_VOICE_STATES
        | Intents::GUILD_PRESENCES
        | Intents::GUILD_SCHEDULED_EVENTS,
    );
});

it('refuses to start without a bot token', function (): void {
    config()->set('discord.bot_token', '');

    $this->artisan('bot:run')
        ->expectsOutputToContain('DISCORD_BOT_TOKEN')
        ->assertFailed();
});

it('reports the bot status', function (): void {
    $this->artisan('bot:status')->expectsOutputToContain('offline')->assertFailed();

    app(BotHeartbeat::class)->beat();

    $this->artisan('bot:status')->expectsOutputToContain('online')->assertSuccessful();
});

it('refuses to start when discord rejects the bot token', function (): void {
    config()->set('discord.bot_token', 'bad-token');
    Http::fake(['discord.com/api/v10/users/@me' => Http::response(['message' => '401: Unauthorized', 'code' => 0], 401)]);

    $this->artisan('bot:run')
        ->expectsOutputToContain('rejected')
        ->assertFailed();
});
