<?php

declare(strict_types=1);

namespace App\Quotes\Imports;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\QuoteImport;
use App\Quotes\Jobs\ImportChannelQuotes;

/**
 * Starts (or resumes) a backfill of a channel's history.
 */
final readonly class StartChannelImport
{
    public function handle(Guild $guild, DiscordUser $requestedBy, string $channelId): QuoteImport
    {
        $import = QuoteImport::query()->create([
            'guild_id' => $guild->id,
            'channel_id' => $channelId,
            'requested_by_discord_user_id' => $requestedBy->id,
            'status' => 'queued',
        ]);

        dispatch(new ImportChannelQuotes($import->id));

        return $import;
    }
}
