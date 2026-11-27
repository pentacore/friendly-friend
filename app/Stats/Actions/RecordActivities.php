<?php

declare(strict_types=1);

namespace App\Stats\Actions;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\StatActivitySession;
use Carbon\CarbonImmutable;

/**
 * Brings a member's game sessions in line with what they play now: games
 * they stopped are closed, new ones are opened, ongoing ones are kept.
 */
final readonly class RecordActivities
{
    /**
     * @param  array<string, int>  $playing  activity name => Discord activity type
     * @param  CarbonImmutable|null  $closedAt  when stopped games ended, if earlier than $at (bot downtime)
     */
    public function handle(Guild $guild, DiscordUser $member, array $playing, CarbonImmutable $at, ?CarbonImmutable $closedAt = null): void
    {
        $open = StatActivitySession::query()
            ->where('guild_id', $guild->id)
            ->where('discord_user_id', $member->id)
            ->whereNull('ended_at')
            ->get();

        foreach ($open as $session) {
            if (! array_key_exists($session->activity_name, $playing)) {
                $session->update(['ended_at' => ($closedAt ?? $at)->max($session->started_at)]);
            }
        }

        $alreadyOpen = $open->pluck('activity_name')->all();

        foreach ($playing as $name => $type) {
            $name = (string) $name;

            if (! in_array($name, $alreadyOpen, true)) {
                StatActivitySession::query()->create([
                    'guild_id' => $guild->id,
                    'discord_user_id' => $member->id,
                    'activity_name' => $name,
                    'activity_type' => $type,
                    'started_at' => $at,
                ]);
            }
        }
    }
}
