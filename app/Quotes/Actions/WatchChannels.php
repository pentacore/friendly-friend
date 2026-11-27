<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\QuoteImport;
use App\Quotes\WatchedChannels;

/**
 * Sets which channels of a server are watched for quotes posted without
 * the bot. Each watched channel has a standing import (status `watching`)
 * that collects what's picked up, so it's reviewed like any import;
 * unwatching a channel finishes its import.
 */
final readonly class WatchChannels
{
    public function __construct(private WatchedChannels $watched) {}

    /**
     * @param  list<string>  $channelIds
     */
    public function handle(Guild $guild, array $channelIds, DiscordUser $actor): void
    {
        $channelIds = array_values(array_unique($channelIds));
        $guild->update(['watched_channel_ids' => $channelIds]);

        QuoteImport::query()
            ->where('guild_id', $guild->id)
            ->where('status', QuoteImport::WATCHING)
            ->whereNotIn('channel_id', $channelIds)
            ->update(['status' => 'done', 'finished_at' => now()]);

        foreach ($channelIds as $channelId) {
            QuoteImport::query()->firstOrCreate(
                ['guild_id' => $guild->id, 'channel_id' => $channelId, 'status' => QuoteImport::WATCHING],
                ['requested_by_discord_user_id' => $actor->id, 'started_at' => now()],
            );
        }

        $this->watched->refresh();
    }
}
