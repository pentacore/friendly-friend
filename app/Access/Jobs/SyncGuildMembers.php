<?php

declare(strict_types=1);

namespace App\Access\Jobs;

use App\Access\Actions\UpsertDiscordMember;
use App\Discord\DiscordClient;
use App\Models\DiscordMember;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Log;

/**
 * Refreshes every guild member from Discord (bot token) and marks members
 * Discord no longer lists as gone. `guild.access` then logs out anyone who
 * lost access.
 */
#[Tries(3)]
#[UniqueFor(600)]
final class SyncGuildMembers implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(DiscordClient $discord, UpsertDiscordMember $upsert): void
    {
        $guildId = config()->string('discord.guild_id');

        if (! $discord->configured() || $guildId === '') {
            Log::warning('Skipping guild member sync: DISCORD_BOT_TOKEN or DISCORD_GUILD_ID is not set.');

            return;
        }

        $seen = [];

        foreach ($discord->guildMembers($guildId) as $member) {
            $upsert->handle($member);
            $seen[] = $member->discordId;
        }

        // An empty answer means something is off on Discord's side, not
        // that everyone left; never lock the whole server out because of it.
        if ($seen === []) {
            Log::warning('Guild member sync returned no members; leaving existing members untouched.');

            return;
        }

        DiscordMember::query()
            ->where('in_guild', true)
            ->whereNotIn('discord_id', $seen)
            ->update(['in_guild' => false]);
    }
}
