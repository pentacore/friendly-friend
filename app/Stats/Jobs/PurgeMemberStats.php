<?php

declare(strict_types=1);

namespace App\Stats\Jobs;

use App\Models\StatActivitySession;
use App\Models\StatDailyActivity;
use App\Models\StatDailyVoice;
use App\Models\StatDailyVoicePair;
use App\Models\StatMessage;
use App\Models\StatReaction;
use App\Models\StatVoiceSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\DB;

/**
 * Deletes every stats row about one member after they opt out: their
 * counters, sessions and rollups, the pairs they are part of, and their
 * name on other people's reactions (those are kept, without a target).
 * Rollups are per member, so nothing else needs rebuilding.
 */
#[Tries(3)]
final class PurgeMemberStats implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $discordUserId) {}

    public function handle(): void
    {
        $id = $this->discordUserId;

        DB::transaction(function () use ($id): void {
            StatMessage::query()->where('discord_user_id', $id)->delete();
            StatVoiceSession::query()->where('discord_user_id', $id)->delete();
            StatActivitySession::query()->where('discord_user_id', $id)->delete();
            StatDailyVoice::query()->where('discord_user_id', $id)->delete();
            StatDailyActivity::query()->where('discord_user_id', $id)->delete();
            StatDailyVoicePair::query()->where('first_discord_user_id', $id)->orWhere('second_discord_user_id', $id)->delete();
            StatReaction::query()->where('reactor_discord_user_id', $id)->delete();

            // Fold reactions *on* their messages into the reactor's
            // "unknown target" row, then drop the targeted rows.
            DB::statement(<<<'SQL'
                insert into stat_reactions (guild_id, reactor_discord_user_id, target_discord_user_id, emoji, day, count)
                select guild_id, reactor_discord_user_id, null, emoji, day, sum(count)
                from stat_reactions
                where target_discord_user_id = ?
                group by guild_id, reactor_discord_user_id, emoji, day
                on conflict (guild_id, reactor_discord_user_id, target_discord_user_id, emoji, day)
                do update set count = stat_reactions.count + excluded.count
                SQL, [$id]);

            StatReaction::query()->where('target_discord_user_id', $id)->delete();
        });
    }
}
