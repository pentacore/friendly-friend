<?php

declare(strict_types=1);

use App\Discord\Data\DiscordUserData;
use App\Discord\Gateway\BotHeartbeat;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\StatActivitySession;
use App\Models\StatMessage;
use App\Models\StatReaction;
use App\Models\StatVoiceSession;
use App\Stats\Jobs\IngestMessage;
use App\Stats\Jobs\IngestPresence;
use App\Stats\Jobs\IngestReaction;
use App\Stats\Jobs\IngestVoiceState;
use App\Stats\Jobs\SeedGuildSessions;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    $this->guild = Guild::factory()->create(['discord_id' => '555']);
    $this->member = DiscordUser::factory()->create(['discord_id' => '42']);
});

function author(string $id = '42'): DiscordUserData
{
    return new DiscordUserData($id, 'martin', 'Martin', null);
}

it('counts messages per member, channel and hour', function (): void {
    dispatch_sync(new IngestMessage('555', author(), '777', '2026-11-20T21:15:00+00:00'));
    dispatch_sync(new IngestMessage('555', author(), '777', '2026-11-20T21:59:59+00:00'));
    dispatch_sync(new IngestMessage('555', author(), '777', '2026-11-20T22:00:00+00:00'));

    expect(StatMessage::query()->orderBy('hour')->get()->map(fn (StatMessage $row): array => [$row->hour->toIso8601String(), $row->count])->all())
        ->toBe([['2026-11-20T21:00:00+00:00', 2], ['2026-11-20T22:00:00+00:00', 1]]);
});

it('records new authors so members who never logged in count too', function (): void {
    dispatch_sync(new IngestMessage('555', author('77'), '777', '2026-11-20T21:15:00+00:00'));

    expect(DiscordUser::query()->where('discord_id', '77')->exists())->toBeTrue()
        ->and(StatMessage::query()->count())->toBe(1);
});

it('stores nothing for opted-out members or disabled servers, even if the bot let it through', function (): void {
    $this->member->forceFill(['tracking_opted_out_at' => now()])->save();
    Guild::factory()->disabled()->create(['discord_id' => '556']);
    DiscordUser::factory()->create(['discord_id' => '43']);

    dispatch_sync(new IngestMessage('555', author(), '777', '2026-11-20T21:15:00+00:00'));
    dispatch_sync(new IngestReaction('555', '42', null, '😂', '2026-11-20T21:15:00+00:00'));
    dispatch_sync(new IngestVoiceState('555', '42', '700', '2026-11-20T21:15:00+00:00'));
    dispatch_sync(new IngestPresence('555', '42', ['Tetris' => 0], '2026-11-20T21:15:00+00:00'));
    dispatch_sync(new IngestMessage('556', author('43'), '777', '2026-11-20T21:15:00+00:00'));

    expect(StatMessage::query()->count() + StatReaction::query()->count() + StatVoiceSession::query()->count() + StatActivitySession::query()->count())->toBe(0);
});

it('counts reactions per day in the stats timezone, with the target when tracked', function (): void {
    $target = DiscordUser::factory()->create(['discord_id' => '50']);
    DiscordUser::factory()->create(['discord_id' => '51', 'tracking_opted_out_at' => now()]);

    // 23:30 UTC is already the next day in Stockholm.
    dispatch_sync(new IngestReaction('555', '42', '50', '😂', '2026-11-20T23:30:00+00:00'));
    dispatch_sync(new IngestReaction('555', '42', '50', '😂', '2026-11-20T23:40:00+00:00'));
    dispatch_sync(new IngestReaction('555', '42', '51', '😂', '2026-11-20T23:40:00+00:00'));
    dispatch_sync(new IngestReaction('555', '42', null, '😂', '2026-11-20T23:40:00+00:00'));

    expect(StatReaction::query()->orderBy('id')->get()->map(fn (StatReaction $row): array => [$row->target_discord_user_id, $row->day->toDateString(), $row->count])->all())
        ->toBe([[$target->id, '2026-11-21', 2], [null, '2026-11-21', 2]]);
});

it('opens, moves and closes voice sessions', function (): void {
    dispatch_sync(new IngestVoiceState('555', '42', '700', '2026-11-20T20:00:00+00:00'));
    dispatch_sync(new IngestVoiceState('555', '42', '700', '2026-11-20T20:10:00+00:00')); // muted, same channel
    dispatch_sync(new IngestVoiceState('555', '42', '701', '2026-11-20T20:30:00+00:00'));
    dispatch_sync(new IngestVoiceState('555', '42', null, '2026-11-20T21:00:00+00:00'));

    expect(StatVoiceSession::query()->orderBy('id')->get()->map(fn (StatVoiceSession $s): array => [$s->channel_id, $s->started_at->format('H:i'), $s->ended_at?->format('H:i')])->all())
        ->toBe([['700', '20:00', '20:30'], ['701', '20:30', '21:00']]);
});

it('keeps ongoing games and closes the ones that stopped', function (): void {
    dispatch_sync(new IngestPresence('555', '42', ['Tetris' => 0], '2026-11-20T20:00:00+00:00'));
    dispatch_sync(new IngestPresence('555', '42', ['Tetris' => 0, 'Doom' => 0], '2026-11-20T20:30:00+00:00'));
    dispatch_sync(new IngestPresence('555', '42', [], '2026-11-20T21:00:00+00:00'));

    expect(StatActivitySession::query()->orderBy('id')->get()->map(fn (StatActivitySession $s): array => [$s->activity_name, $s->started_at->format('H:i'), $s->ended_at?->format('H:i')])->all())
        ->toBe([['Tetris', '20:00', '21:00'], ['Doom', '20:30', '21:00']]);
});

it('reseeds sessions after a reconnect: keeps matches, closes stale ones, opens missing ones', function (): void {
    $friend = DiscordUser::factory()->create(['discord_id' => '43']);
    $left = DiscordUser::factory()->create(['discord_id' => '44']);
    $kept = StatVoiceSession::factory()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $this->member->id, 'channel_id' => '700']);
    $stale = StatVoiceSession::factory()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $left->id, 'channel_id' => '700']);
    $game = StatActivitySession::factory()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $left->id, 'activity_name' => 'Tetris']);

    dispatch_sync(new SeedGuildSessions('555', ['42' => '700', '43' => '701'], ['43' => ['Doom' => 0]], now()->toIso8601String()));

    expect($kept->fresh()?->ended_at)->toBeNull()
        ->and($stale->fresh()?->ended_at)->not->toBeNull()
        ->and($game->fresh()?->ended_at)->not->toBeNull()
        ->and(StatVoiceSession::query()->where('discord_user_id', $friend->id)->whereNull('ended_at')->value('channel_id'))->toBe('701')
        ->and(StatActivitySession::query()->where('discord_user_id', $friend->id)->whereNull('ended_at')->value('activity_name'))->toBe('Doom');
});

it('ends sessions that stopped during bot downtime at the bot\'s last sign of life', function (): void {
    $left = DiscordUser::factory()->create(['discord_id' => '44']);
    $stale = StatVoiceSession::factory()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $left->id, 'channel_id' => '700', 'started_at' => '2026-11-20T18:00:00Z']);

    dispatch_sync(new SeedGuildSessions('555', [], [], '2026-11-20T23:00:00+00:00', '2026-11-20T19:30:00+00:00'));

    expect($stale->fresh()?->ended_at?->toIso8601String())->toBe('2026-11-20T19:30:00+00:00');
});

it('remembers the previous heartbeat only right after a restart', function (): void {
    $heartbeat = app(BotHeartbeat::class);
    Date::setTestNow('2026-11-20T19:30:00Z');
    $heartbeat->beat();

    Date::setTestNow('2026-11-20T23:00:00Z');
    $heartbeat->markStarted();
    $heartbeat->beat();

    expect($heartbeat->downSince()?->toIso8601String())->toBe('2026-11-20T19:30:00+00:00');

    Date::setTestNow('2026-11-20T23:30:00Z');
    expect($heartbeat->downSince())->toBeNull();
});
