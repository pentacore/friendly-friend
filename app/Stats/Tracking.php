<?php

declare(strict_types=1);

namespace App\Stats;

use App\Models\DiscordUser;
use App\Models\Guild;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The checks every stats job makes before writing: the server is enabled
 * and the member is known and has not opted out (checked against the
 * database, the second of the two opt-out checks).
 */
final readonly class Tracking
{
    public function guild(string $guildDiscordId): ?Guild
    {
        return Guild::query()->enabled()->where('discord_id', $guildDiscordId)->first();
    }

    public function member(string $discordId): ?DiscordUser
    {
        return DiscordUser::query()
            ->where('discord_id', $discordId)
            ->whereNull('tracking_opted_out_at')
            ->first();
    }

    /**
     * Tracked members by Discord ID.
     *
     * @param  list<string>  $discordIds
     * @return Collection<string, DiscordUser>
     */
    public function members(array $discordIds): Collection
    {
        return DiscordUser::query()
            ->whereIn('discord_id', $discordIds)
            ->whereNull('tracking_opted_out_at')
            ->get()
            ->keyBy(fn (DiscordUser $user): string => $user->discord_id);
    }

    /**
     * The calendar day of a moment, in the stats timezone.
     */
    public static function day(CarbonImmutable $at): string
    {
        return $at->setTimezone(config()->string('stats.timezone'))->toDateString();
    }
}
