<?php

declare(strict_types=1);

namespace App\Quotes;

use App\Models\Guild;
use Illuminate\Support\Facades\Cache;

/**
 * Discord IDs of servers where `/quote random` answers only the person who
 * asked, cached so the interaction acknowledgement can decide without a
 * database query. Refreshed when server settings change and when the bot starts.
 */
final readonly class PrivateRandomQuotes
{
    private const string KEY = 'quotes:private-random-guilds';

    public function contains(string $guildDiscordId): bool
    {
        $ids = Cache::get(self::KEY);

        return is_array($ids) && in_array($guildDiscordId, $ids, true);
    }

    public function refresh(): void
    {
        Cache::forever(self::KEY, Guild::query()->where('private_random_quotes', true)->pluck('discord_id')->all());
    }
}
