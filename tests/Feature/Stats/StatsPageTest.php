<?php

declare(strict_types=1);

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\Person;
use App\Models\StatDailyActivity;
use App\Models\StatDailyVoice;
use App\Models\StatDailyVoicePair;
use App\Models\StatMessage;
use App\Models\StatReaction;
use App\Models\StatVoiceSession;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Date::setTestNow('2026-11-21T12:00:00Z');
    $this->guild = Guild::factory()->create(['name' => 'Friends']);
    $this->other = Guild::factory()->create(['name' => 'Strangers']);
    $this->user = User::factory()->memberOf($this->guild)->create();
    [$this->bo, $this->kim] = DiscordUser::factory()->count(2)->create()->all();
});

function messages(Guild $guild, DiscordUser $member, int $count, string $hour = '2026-11-20T19:00:00Z'): void
{
    StatMessage::factory()->create(['guild_id' => $guild->id, 'discord_user_id' => $member->id, 'hour' => $hour, 'count' => $count]);
}

it('shows leaderboards for the servers you can see only', function (): void {
    messages($this->guild, $this->bo, 5);
    messages($this->guild, $this->kim, 9);
    messages($this->other, $this->bo, 100);
    Person::factory()->create(['discord_user_id' => $this->kim->id, 'slug' => 'kim']);

    $this->actingAs($this->user)->get(route('stats'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('stats/Index')
            ->where('guilds', [['id' => $this->guild->id, 'name' => 'Friends']])
            ->where('totals.messages', 14)
            ->where('totals.members', 2)
            ->where('messages.0.member.id', $this->kim->id)
            ->where('messages.0.member.person_slug', 'kim')
            ->where('messages.0.total', 9)
            ->where('messages.1.total', 5));
});

it('ignores a server you cannot see', function (): void {
    messages($this->other, $this->bo, 100);

    $this->actingAs($this->user)->get(route('stats', ['guild' => $this->other->id]))
        ->assertInertia(fn (Assert $page): Assert => $page->where('totals.messages', 0)->where('filters.guild', null));
});

it('limits everything to the chosen period', function (): void {
    messages($this->guild, $this->bo, 3, '2026-11-20T19:00:00Z');
    messages($this->guild, $this->bo, 50, '2026-10-01T19:00:00Z');

    $this->actingAs($this->user)->get(route('stats', ['days' => 7]))
        ->assertInertia(fn (Assert $page): Assert => $page->where('totals.messages', 3)->where('filters.days', 7));

    $this->actingAs($this->user)->get(route('stats', ['days' => 365]))
        ->assertInertia(fn (Assert $page): Assert => $page->where('totals.messages', 53));
});

it('buckets the heatmap by local weekday and hour', function (): void {
    // Friday 2026-11-20 19:00 UTC is 20:00 in Stockholm.
    messages($this->guild, $this->bo, 4, '2026-11-20T19:00:00Z');

    $this->actingAs($this->user)->get(route('stats'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('heatmap', [['weekday' => 5, 'hour' => 20, 'count' => 4]]));
});

it('fills the voice, pair, emoji, reaction and game boards', function (): void {
    $day = '2026-11-20';
    StatDailyVoice::query()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $this->bo->id, 'day' => $day, 'seconds' => 7200]);
    StatDailyVoicePair::query()->create(['guild_id' => $this->guild->id, 'first_discord_user_id' => $this->bo->id, 'second_discord_user_id' => $this->kim->id, 'day' => $day, 'seconds' => 3600]);
    StatDailyActivity::query()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $this->bo->id, 'activity_name' => 'Doom', 'day' => $day, 'seconds' => 1800]);
    StatReaction::factory()->create(['guild_id' => $this->guild->id, 'reactor_discord_user_id' => $this->bo->id, 'target_discord_user_id' => $this->kim->id, 'emoji' => '123:kekw', 'day' => $day, 'count' => 6]);
    StatReaction::factory()->create(['guild_id' => $this->guild->id, 'reactor_discord_user_id' => $this->bo->id, 'target_discord_user_id' => $this->bo->id, 'emoji' => '😂', 'day' => $day, 'count' => 1]);

    $this->actingAs($this->user)->get(route('stats'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('voice.0.total', 7200)
            ->where('voice_pairs.0.total', 3600)
            ->where('games', [['name' => 'Doom', 'total' => 1800, 'players' => 1]])
            ->where('emoji.0.name', 'kekw')
            ->where('emoji.0.image_url', 'https://cdn.discordapp.com/emojis/123.webp?size=64')
            ->where('emoji.1.image_url', null)
            ->where('reactors.0.total', 7)
            ->has('reaction_pairs', 1)
            ->where('reaction_pairs.0.second.id', $this->kim->id));
});

it('shows a person\'s stats unless they opted out', function (): void {
    $person = Person::factory()->create(['discord_user_id' => $this->bo->id, 'slug' => 'bo']);
    messages($this->guild, $this->bo, 5);

    $this->actingAs($this->user)->get(route('people.show', 'bo'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('stats.totals.messages', 5)->where('stats.days', 30));

    $this->bo->forceFill(['tracking_opted_out_at' => now()])->save();

    $this->actingAs($this->user)->get(route('people.show', $person->slug))
        ->assertInertia(fn (Assert $page): Assert => $page->where('stats', null));
});

it('names the busiest channels from the server\'s channel list', function (): void {
    $this->guild->update(['channels' => [['id' => '777', 'name' => 'general', 'position' => 1]]]);
    StatMessage::factory()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $this->bo->id, 'channel_id' => '777', 'hour' => '2026-11-20T19:00:00Z', 'count' => 9]);
    StatMessage::factory()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $this->bo->id, 'channel_id' => '999', 'hour' => '2026-11-20T19:00:00Z', 'count' => 2]);

    $this->actingAs($this->user)->get(route('stats'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('channels', [
            ['channel_id' => '777', 'name' => '#general', 'guild' => 'Friends', 'total' => 9],
            ['channel_id' => '999', 'name' => 'a hidden channel', 'guild' => 'Friends', 'total' => 2],
        ]));
});

it('shows this week on the dashboard, and nothing when nothing was counted', function (): void {
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('week', null));

    messages($this->guild, $this->bo, 5);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('week.messages', 5)
            ->where('week.top.name', $this->bo->display_name));
});

it('shows records: longest voice session, busiest day and longest streak', function (): void {
    StatVoiceSession::factory()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $this->bo->id, 'started_at' => '2026-11-18T18:00:00Z', 'ended_at' => '2026-11-18T21:30:00Z']);
    StatVoiceSession::factory()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $this->kim->id, 'started_at' => '2026-11-21T09:00:00Z', 'ended_at' => null]);
    StatVoiceSession::factory()->create(['guild_id' => $this->other->id, 'discord_user_id' => $this->kim->id, 'started_at' => '2026-11-10T00:00:00Z', 'ended_at' => '2026-11-11T00:00:00Z']);

    // Kim: 30 messages on one local day (23:30 UTC on the 19th is the 20th in Stockholm).
    messages($this->guild, $this->kim, 10, '2026-11-19T23:30:00Z');
    messages($this->guild, $this->kim, 20, '2026-11-20T08:00:00Z');
    // Bo: a four-day streak (16th–19th), then a gap, then one more day.
    foreach (['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19'] as $day) {
        messages($this->guild, $this->bo, 2, "{$day}T12:00:00Z");
    }
    messages($this->guild, $this->bo, 2, '2026-11-21T10:00:00Z');

    $this->actingAs($this->user)->get(route('stats'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('records.longest_voice.member.id', $this->bo->id)
            ->where('records.longest_voice.seconds', 12600)
            ->where('records.busiest_day.member.id', $this->kim->id)
            ->where('records.busiest_day.total', 30)
            ->where('records.busiest_day.day', '2026-11-20')
            ->where('records.longest_streak.member.id', $this->bo->id)
            ->where('records.longest_streak.days', 4)
            ->where('records.longest_streak.ended_on', '2026-11-19'));
});

it('has no records without activity', function (): void {
    $this->actingAs($this->user)->get(route('stats'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('records', ['longest_voice' => null, 'busiest_day' => null, 'longest_streak' => null]));
});
