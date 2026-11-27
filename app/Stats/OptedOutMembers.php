<?php

declare(strict_types=1);

namespace App\Stats;

use App\Models\DiscordUser;
use Illuminate\Support\Facades\Cache;

/**
 * The Discord IDs of everyone who opted out of stats, cached so the bot
 * process can drop their events without a database query. The jobs check
 * the database again, so a cold or stale cache never stores anything.
 */
final readonly class OptedOutMembers
{
    private const string KEY = 'stats:opted-out';

    public function contains(string $discordId): bool
    {
        $ids = Cache::get(self::KEY);

        return is_array($ids) && in_array($discordId, $ids, true);
    }

    /**
     * Rebuild the cached set from the database.
     */
    public function refresh(): void
    {
        Cache::forever(self::KEY, DiscordUser::query()
            ->whereNotNull('tracking_opted_out_at')
            ->get(['discord_id'])
            ->map(fn (DiscordUser $user): string => $user->discord_id)
            ->values()
            ->all());
    }
}
