<?php

declare(strict_types=1);

namespace App\Stats\Jobs;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\StatReaction;
use App\Stats\Tracking;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\DB;

/**
 * Counts one reaction for the reactor, emoji and day, and whose message it
 * was on when Discord says so. A target who opted out is stored as null.
 */
#[Tries(3)]
final class IngestReaction implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $guildDiscordId,
        public readonly string $reactorDiscordId,
        public readonly ?string $targetDiscordId,
        public readonly string $emoji,
        public readonly string $at,
    ) {}

    public function handle(Tracking $tracking): void
    {
        $guild = $tracking->guild($this->guildDiscordId);
        $reactor = $tracking->member($this->reactorDiscordId);

        if (! $guild instanceof Guild || ! $reactor instanceof DiscordUser) {
            return;
        }

        $target = $this->targetDiscordId !== null ? $tracking->member($this->targetDiscordId) : null;

        StatReaction::query()->upsert(
            [[
                'guild_id' => $guild->id,
                'reactor_discord_user_id' => $reactor->id,
                'target_discord_user_id' => $target?->id,
                'emoji' => $this->emoji,
                'day' => Tracking::day(CarbonImmutable::parse($this->at)),
                'count' => 1,
            ]],
            ['guild_id', 'reactor_discord_user_id', 'target_discord_user_id', 'emoji', 'day'],
            ['count' => DB::raw('stat_reactions.count + 1')],
        );
    }
}
