<?php

declare(strict_types=1);

namespace App\Stats\Jobs;

use App\Access\Actions\RecordDiscordUser;
use App\Discord\Data\DiscordUserData;
use App\Models\Guild;
use App\Models\StatMessage;
use App\Stats\Tracking;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\DB;

/**
 * Counts one message (never its content) in its member's, channel's and
 * hour's bucket.
 */
#[Tries(3)]
final class IngestMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $guildDiscordId,
        public readonly DiscordUserData $author,
        public readonly string $channelId,
        public readonly string $sentAt,
    ) {}

    public function handle(Tracking $tracking, RecordDiscordUser $recordUser): void
    {
        $guild = $tracking->guild($this->guildDiscordId);

        if (! $guild instanceof Guild) {
            return;
        }

        $member = $recordUser->handle($this->author);

        if ($member->tracking_opted_out_at !== null) {
            return;
        }

        StatMessage::query()->upsert(
            [[
                'guild_id' => $guild->id,
                'discord_user_id' => $member->id,
                'channel_id' => $this->channelId,
                'hour' => CarbonImmutable::parse($this->sentAt)->utc()->startOfHour(),
                'count' => 1,
            ]],
            ['guild_id', 'discord_user_id', 'channel_id', 'hour'],
            ['count' => DB::raw('stat_messages.count + 1')],
        );
    }
}
