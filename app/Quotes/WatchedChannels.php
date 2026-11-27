<?php

declare(strict_types=1);

namespace App\Quotes;

use App\Models\Guild;
use Illuminate\Support\Facades\Cache;

/**
 * Channel IDs watched for quotes posted without the bot, across enabled
 * servers, cached so the bot process can decide without a database query.
 * Refreshed when server settings change and when the bot starts; the job
 * checks the database again.
 */
final readonly class WatchedChannels
{
    private const string KEY = 'quotes:watched-channels';

    public function contains(string $channelId): bool
    {
        $ids = Cache::get(self::KEY);

        return is_array($ids) && in_array($channelId, $ids, true);
    }

    public function refresh(): void
    {
        Cache::forever(self::KEY, array_values(array_unique(Guild::query()->enabled()->get()
            ->flatMap(fn (Guild $guild): array => $guild->watched_channel_ids)
            ->all())));
    }
}
