<?php

declare(strict_types=1);

namespace App\Access\Jobs;

use App\Access\Actions\RecordGuildMember;
use App\Discord\DiscordClient;
use App\Models\Guild;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Log;

/**
 * Refreshes every member of one enabled guild from Discord (bot token) and
 * marks members Discord no longer lists as gone from that guild.
 */
#[Tries(3)]
#[UniqueFor(600)]
final class SyncGuildMembers implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $guildDiscordId) {}

    public function uniqueId(): string
    {
        return $this->guildDiscordId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(DiscordClient $discord, RecordGuildMember $record): void
    {
        if (! $discord->configured()) {
            Log::warning('Skipping guild member sync: DISCORD_BOT_TOKEN is not set.');

            return;
        }

        $guild = Guild::query()->enabled()->where('discord_id', $this->guildDiscordId)->first();

        if ($guild === null) {
            return;
        }

        $seenMemberIds = [];

        foreach ($discord->guildMembers($guild->discord_id) as $member) {
            if ($member->isValid()) {
                $seenMemberIds[] = $record->handle($guild, $member)->id;
            }
        }

        // An empty answer means something is off on Discord's side, not that
        // everyone left; never lock a whole server out because of it.
        if ($seenMemberIds === []) {
            Log::warning('Guild member sync returned no members; leaving existing members untouched.', ['guild' => $guild->discord_id]);

            return;
        }

        $guild->members()
            ->where('in_guild', true)
            ->whereNotIn('id', $seenMemberIds)
            ->update(['in_guild' => false]);
    }
}
