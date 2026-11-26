<?php

declare(strict_types=1);

namespace App\Access\Jobs;

use App\Models\DiscordMember;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * A member left, was kicked or banned (gateway GUILD_MEMBER_REMOVE). Their
 * row stays for quotes and stats; guild.access logs them out.
 */
#[Tries(3)]
final class MarkMemberLeft implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $discordId) {}

    public function handle(): void
    {
        DiscordMember::query()
            ->where('discord_id', $this->discordId)
            ->update(['in_guild' => false]);
    }
}
