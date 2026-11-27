<?php

declare(strict_types=1);

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\StatActivitySession;
use App\Models\StatDailyActivity;
use App\Models\StatDailyVoice;
use App\Models\StatDailyVoicePair;
use App\Models\StatVoiceSession;
use App\Stats\Jobs\RollUpStats;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    // Stockholm is UTC+1 in November: the 20th runs 19th 23:00 to 20th 23:00 UTC.
    Date::setTestNow('2026-11-21T12:00:00Z');
    $this->guild = Guild::factory()->create();
    [$this->a, $this->b, $this->c] = DiscordUser::factory()->count(3)->create()->all();
});

function voice(DiscordUser $member, string $channel, string $from, ?string $to): void
{
    StatVoiceSession::factory()->create([
        'guild_id' => test()->guild->id,
        'discord_user_id' => $member->id,
        'channel_id' => $channel,
        'started_at' => $from,
        'ended_at' => $to,
    ]);
}

it('sums voice time per member, clipped to the local day', function (): void {
    voice($this->a, '700', '2026-11-19T22:00:00Z', '2026-11-20T01:00:00Z'); // 2 h on the 20th
    voice($this->a, '700', '2026-11-20T20:00:00Z', '2026-11-20T23:30:00Z'); // 3 h on the 20th

    dispatch_sync(new RollUpStats('2026-11-20'));

    expect(StatDailyVoice::query()->sole()->seconds)->toBe(5 * 3600);
});

it('counts sessions that are still open until now', function (): void {
    voice($this->a, '700', '2026-11-21T10:00:00Z', null);

    dispatch_sync(new RollUpStats(daysAgo: 0));

    expect(StatDailyVoice::query()->sole())
        ->day->toDateString()->toBe('2026-11-21')
        ->seconds->toBe(2 * 3600);
});

it('measures time spent together in the same channel', function (): void {
    voice($this->a, '700', '2026-11-20T18:00:00Z', '2026-11-20T21:00:00Z');
    voice($this->b, '700', '2026-11-20T19:00:00Z', '2026-11-20T22:00:00Z');
    voice($this->c, '701', '2026-11-20T18:00:00Z', '2026-11-20T22:00:00Z'); // other channel

    dispatch_sync(new RollUpStats('2026-11-20'));

    $pair = StatDailyVoicePair::query()->sole();
    expect([$pair->first_discord_user_id, $pair->second_discord_user_id, $pair->seconds])
        ->toBe([min($this->a->id, $this->b->id), max($this->a->id, $this->b->id), 2 * 3600]);
});

it('sums game time per member and game', function (): void {
    StatActivitySession::factory()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $this->a->id, 'activity_name' => 'Doom', 'started_at' => '2026-11-20T18:00:00Z', 'ended_at' => '2026-11-20T18:30:00Z']);

    dispatch_sync(new RollUpStats('2026-11-20'));

    expect(StatDailyActivity::query()->sole())->activity_name->toBe('Doom')->seconds->toBe(1800);
});

it('replaces the day when rerun', function (): void {
    voice($this->a, '700', '2026-11-20T18:00:00Z', '2026-11-20T19:00:00Z');

    dispatch_sync(new RollUpStats('2026-11-20'));
    dispatch_sync(new RollUpStats('2026-11-20'));

    expect(StatDailyVoice::query()->count())->toBe(1);
});
