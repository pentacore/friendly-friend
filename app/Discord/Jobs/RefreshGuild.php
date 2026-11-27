<?php

declare(strict_types=1);

namespace App\Discord\Jobs;

use App\Discord\DiscordClient;
use App\Models\Guild;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;

/**
 * Roles changed (GUILD_ROLE_*): re-read the guild over REST and update the
 * stored name, icon and role list. Unique per guild, so a burst of role
 * edits only fetches once.
 */
#[Tries(3)]
#[UniqueFor(60)]
final class RefreshGuild implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $guildDiscordId) {}

    public function uniqueId(): string
    {
        return $this->guildDiscordId;
    }

    public function handle(DiscordClient $discord): void
    {
        if (Guild::query()->where('discord_id', $this->guildDiscordId)->doesntExist()) {
            return;
        }

        new RegisterGuild($discord->guild($this->guildDiscordId), syncMembers: false)->handle();
    }
}
