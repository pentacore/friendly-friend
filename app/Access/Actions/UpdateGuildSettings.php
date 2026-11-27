<?php

declare(strict_types=1);

namespace App\Access\Actions;

use App\Access\Jobs\SyncGuildMembers;
use App\Discord\Jobs\RegisterGuildCommands;
use App\Models\Guild;
use App\Quotes\WatchedChannels;

/**
 * Saves a server's access settings. Enabling a server pulls in its members
 * straight away, so its people can log in without waiting for the schedule.
 */
final readonly class UpdateGuildSettings
{
    public function __construct(private WatchedChannels $watchedChannels) {}

    /**
     * @param  list<string>  $allowedRoleIds
     * @param  list<string>  $adminRoleIds
     */
    public function handle(Guild $guild, bool $enabled, bool $allowAllMembers, array $allowedRoleIds, array $adminRoleIds, ?string $quotesChannelId = null, ?string $eventsChannelId = null): Guild
    {
        $wasEnabled = $guild->enabled;

        $guild->update([
            'enabled' => $enabled,
            'allow_all_members' => $allowAllMembers,
            'allowed_role_ids' => array_values(array_unique($allowedRoleIds)),
            'admin_role_ids' => array_values(array_unique($adminRoleIds)),
            'quotes_channel_id' => $quotesChannelId,
            'events_channel_id' => $eventsChannelId,
        ]);

        if ($enabled && ! $wasEnabled) {
            dispatch(new SyncGuildMembers($guild->discord_id));
        }

        if ($enabled !== $wasEnabled) {
            dispatch(new RegisterGuildCommands($guild->discord_id));
            $this->watchedChannels->refresh();
        }

        return $guild;
    }
}
