<?php

declare(strict_types=1);

namespace App\Planning;

use App\Access\AccessSnapshot;
use App\Models\DiscordUser;
use App\Models\Event;

/**
 * Who may do what with an event. Shared by EventPolicy and the resources
 * (which use the request's AccessSnapshot). Load `hosts` before calling
 * canEdit in a loop.
 */
final class EventRules
{
    public static function canView(AccessSnapshot $access, Event $event): bool
    {
        return $access->canSeeGuild($event->guild_id);
    }

    /**
     * The creator, co-hosts and the server's admins.
     */
    public static function canEdit(AccessSnapshot $access, Event $event): bool
    {
        if (! self::canView($access, $event)) {
            return false;
        }

        return $access->moderates($event->guild_id)
            || $event->created_by_discord_user_id === $access->discordUserId
            || $event->hosts->contains(fn (DiscordUser $host): bool => $host->id === $access->discordUserId);
    }

    /**
     * RSVP, claim, tick, drive, ride and add costs. Not on cancelled events,
     * except costs, which may still need settling.
     */
    public static function canParticipate(AccessSnapshot $access, Event $event): bool
    {
        return self::canView($access, $event) && ! $event->isCancelled();
    }
}
