<?php

declare(strict_types=1);

namespace App\Access\Jobs;

use App\Models\GuildMember;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * A member left, was kicked or banned (GUILD_MEMBER_REMOVE). Their rows stay
 * for quotes and stats; guild.access logs them out if this was their last
 * way in.
 */
#[Tries(3)]
final class MarkMemberLeft implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $guildDiscordId,
        public readonly string $userDiscordId,
    ) {}

    public function handle(): void
    {
        GuildMember::query()
            ->whereHas('guild', fn (Builder $query) => $query->where('discord_id', $this->guildDiscordId))
            ->whereHas('discordUser', fn (Builder $query) => $query->where('discord_id', $this->userDiscordId))
            ->update(['in_guild' => false]);
    }
}
