<?php

declare(strict_types=1);

use App\Discord\Gateway\BotHeartbeat;
use App\Discord\Gateway\GatewayIntents;
use Discord\WebSockets\Intents;
use Illuminate\Support\Facades\Date;

it('is online right after a heartbeat', function (): void {
    $heartbeat = app(BotHeartbeat::class);

    expect($heartbeat->isOnline())->toBeFalse()
        ->and($heartbeat->lastBeatAt())->toBeNull();

    $heartbeat->beat();

    expect($heartbeat->isOnline())->toBeTrue()
        ->and($heartbeat->lastBeatAt()?->timestamp)->toBe(Date::now()->timestamp);
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
        | Intents::GUILD_PRESENCES,
    );
});

it('refuses to start without a bot token', function (): void {
    config()->set('discord.bot_token', '');
    config()->set('discord.guild_id', '555');

    $this->artisan('bot:run')
        ->expectsOutputToContain('DISCORD_BOT_TOKEN')
        ->assertFailed();
});

it('refuses to start without a guild id', function (): void {
    config()->set('discord.bot_token', 'token');
    config()->set('discord.guild_id', '');

    $this->artisan('bot:run')
        ->expectsOutputToContain('DISCORD_GUILD_ID')
        ->assertFailed();
});

it('reports the bot status', function (): void {
    $this->artisan('bot:status')->expectsOutputToContain('offline')->assertFailed();

    app(BotHeartbeat::class)->beat();

    $this->artisan('bot:status')->expectsOutputToContain('online')->assertSuccessful();
});
