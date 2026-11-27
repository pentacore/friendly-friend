<?php

declare(strict_types=1);

namespace App\Discord\Jobs;

use App\Access\Jobs\SyncGuildMembers;
use App\Discord\Data\GuildData;
use App\Discord\Data\GuildRoleData;
use App\Models\Guild;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * The bot joined a guild or (re)connected to it (GUILD_CREATE / _UPDATE):
 * store its name, icon and roles. New guilds start disabled until an owner
 * enables them; enabled guilds get a member sync when the bot connects.
 */
#[Tries(3)]
final class RegisterGuild implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly GuildData $guild,
        public readonly bool $syncMembers = true,
    ) {}

    public function handle(): void
    {
        $guild = Guild::query()->updateOrCreate(
            ['discord_id' => $this->guild->discordId],
            [
                'name' => $this->guild->name,
                'icon' => $this->guild->icon,
                'roles' => array_map(fn (GuildRoleData $role): array => $role->toArray(), $this->guild->roles),
                'bot_present' => true,
                // REST guild objects carry no channels: keep the last known list.
                ...($this->guild->channels !== [] ? ['channels' => $this->guild->channels] : []),
            ],
        );

        if ($this->syncMembers && $guild->enabled) {
            dispatch(new SyncGuildMembers($guild->discord_id));
        }
    }
}
