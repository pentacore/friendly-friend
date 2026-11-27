<?php

declare(strict_types=1);

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\StatActivitySession;
use App\Models\StatDailyVoicePair;
use App\Models\StatMessage;
use App\Models\StatReaction;
use App\Models\StatVoiceSession;
use App\Stats\Actions\OptIntoStats;
use App\Stats\Actions\OptOutOfStats;
use App\Stats\Jobs\PurgeMemberStats;
use App\Stats\OptedOutMembers;
use Illuminate\Support\Facades\Queue;

it('marks the member, caches the opt-out and queues the purge', function (): void {
    Queue::fake();
    $member = DiscordUser::factory()->create();

    app(OptOutOfStats::class)->handle($member);

    expect($member->fresh()?->tracking_opted_out_at)->not->toBeNull()
        ->and(app(OptedOutMembers::class)->contains($member->discord_id))->toBeTrue();
    Queue::assertPushed(PurgeMemberStats::class, fn (PurgeMemberStats $job): bool => $job->discordUserId === $member->id);
});

it('opts back in without bringing anything back', function (): void {
    Queue::fake();
    $member = DiscordUser::factory()->create();
    app(OptOutOfStats::class)->handle($member);

    app(OptIntoStats::class)->handle($member);

    expect($member->fresh()?->tracking_opted_out_at)->toBeNull()
        ->and(app(OptedOutMembers::class)->contains($member->discord_id))->toBeFalse();
});

it('treats a cold cache as nobody opted out (the jobs check the database)', function (): void {
    expect(app(OptedOutMembers::class)->contains('123'))->toBeFalse();
});

it('purges every stats row about the member and keeps other people\'s data', function (): void {
    $guild = Guild::factory()->create();
    [$gone, $friend] = DiscordUser::factory()->count(2)->create()->all();

    StatMessage::factory()->create(['guild_id' => $guild->id, 'discord_user_id' => $gone->id]);
    StatMessage::factory()->create(['guild_id' => $guild->id, 'discord_user_id' => $friend->id]);
    StatVoiceSession::factory()->create(['guild_id' => $guild->id, 'discord_user_id' => $gone->id]);
    StatActivitySession::factory()->create(['guild_id' => $guild->id, 'discord_user_id' => $gone->id]);
    StatDailyVoicePair::query()->create(['guild_id' => $guild->id, 'first_discord_user_id' => $friend->id, 'second_discord_user_id' => $gone->id, 'day' => today(), 'seconds' => 60]);
    StatReaction::factory()->create(['guild_id' => $guild->id, 'reactor_discord_user_id' => $gone->id]);
    StatReaction::factory()->create(['guild_id' => $guild->id, 'reactor_discord_user_id' => $friend->id, 'target_discord_user_id' => $gone->id, 'count' => 2]);
    StatReaction::factory()->create(['guild_id' => $guild->id, 'reactor_discord_user_id' => $friend->id, 'target_discord_user_id' => null, 'count' => 3]);

    dispatch_sync(new PurgeMemberStats($gone->id));

    expect(StatMessage::query()->pluck('discord_user_id')->all())->toBe([$friend->id])
        ->and(StatVoiceSession::query()->count())->toBe(0)
        ->and(StatActivitySession::query()->count())->toBe(0)
        ->and(StatDailyVoicePair::query()->count())->toBe(0)
        ->and(StatReaction::query()->get(['reactor_discord_user_id', 'target_discord_user_id', 'count'])->toArray())
        ->toBe([['reactor_discord_user_id' => $friend->id, 'target_discord_user_id' => null, 'count' => 5]]);
});
