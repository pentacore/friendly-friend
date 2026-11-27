<?php

declare(strict_types=1);

namespace App\Discord\Jobs;

use App\Models\Guild;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The bot was removed from a guild (GUILD_DELETE without `unavailable`).
 * The guild and its data stay; its members keep access only while it is
 * enabled, which owners can change on /admin/guilds.
 */
final class MarkGuildLeft implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $guildDiscordId) {}

    public function handle(): void
    {
        Guild::query()->where('discord_id', $this->guildDiscordId)->update(['bot_present' => false]);
    }
}
