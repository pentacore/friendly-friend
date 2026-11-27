<?php

declare(strict_types=1);

namespace App\Stats\Actions;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\StatVoiceSession;
use Carbon\CarbonImmutable;

/**
 * Moves a member's voice session to where they are now: nothing changes
 * if they are still in the same channel, otherwise the open session is
 * closed and, if they are in a channel, a new one starts.
 */
final readonly class RecordVoiceState
{
    /**
     * @param  CarbonImmutable|null  $closedAt  when the old session ended, if earlier than $at (bot downtime)
     */
    public function handle(Guild $guild, DiscordUser $member, ?string $channelId, CarbonImmutable $at, ?CarbonImmutable $closedAt = null): void
    {
        $open = StatVoiceSession::query()
            ->where('guild_id', $guild->id)
            ->where('discord_user_id', $member->id)
            ->whereNull('ended_at')
            ->first();

        if ($open !== null && $open->channel_id === $channelId) {
            return;
        }

        if ($open !== null) {
            $open->update(['ended_at' => ($closedAt ?? $at)->max($open->started_at)]);
        }

        if ($channelId !== null) {
            StatVoiceSession::query()->create([
                'guild_id' => $guild->id,
                'discord_user_id' => $member->id,
                'channel_id' => $channelId,
                'started_at' => $at,
            ]);
        }
    }
}
