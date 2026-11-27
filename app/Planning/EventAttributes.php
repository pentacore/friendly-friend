<?php

declare(strict_types=1);

namespace App\Planning;

use App\Models\Event;
use App\Models\GuildMember;
use App\Planning\Data\EventDetails;

/**
 * What CreateEvent and UpdateEvent share: the event form's columns, and
 * co-hosts limited to current members of the event's server.
 */
final readonly class EventAttributes
{
    /**
     * @return array<string, mixed>
     */
    public function columns(EventDetails $details): array
    {
        return [
            'title' => $details->title,
            'description' => $details->description,
            'location' => $details->location,
            'location_url' => $details->locationUrl,
            'starts_at' => $details->startsAt,
            'ends_at' => $details->endsAt,
            'timezone' => $details->timezone,
        ];
    }

    /**
     * @param  list<int>  $hostIds
     */
    public function syncHosts(Event $event, array $hostIds): void
    {
        $members = GuildMember::query()
            ->where('guild_id', $event->guild_id)
            ->where('in_guild', true)
            ->whereIn('discord_user_id', $hostIds)
            ->where('discord_user_id', '!=', $event->created_by_discord_user_id)
            ->pluck('discord_user_id');

        $event->hosts()->sync($members->all());
    }
}
