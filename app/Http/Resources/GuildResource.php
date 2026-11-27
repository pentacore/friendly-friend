<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Guild;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * A server as shown on /admin/guilds.
 */
final class GuildResource extends JsonResource
{
    public function __construct(private readonly Guild $guild)
    {
        parent::__construct($guild);
    }

    /**
     * @return array{
     *     id: int,
     *     discord_id: string,
     *     name: string,
     *     icon_url: string|null,
     *     bot_present: bool,
     *     enabled: bool,
     *     allow_all_members: bool,
     *     allowed_role_ids: list<string>,
     *     admin_role_ids: list<string>,
     *     roles: list<array{id: string, name: string, color: int, position: int, managed: bool}>,
     *     channels: list<array{id: string, name: string, position: int}>,
     *     quotes_channel_id: string|null,
     *     events_channel_id: string|null,
     *     watched_channel_ids: list<string>,
     *     publish_watched_quotes: bool,
     *     member_count: int,
     * }
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $guild = $this->guild;

        return [
            'id' => $guild->id,
            'discord_id' => $guild->discord_id,
            'name' => $guild->name,
            'icon_url' => $guild->iconUrl(),
            'bot_present' => $guild->bot_present,
            'enabled' => $guild->enabled,
            'allow_all_members' => $guild->allow_all_members,
            'allowed_role_ids' => $guild->allowed_role_ids,
            'admin_role_ids' => $guild->admin_role_ids,
            'roles' => $guild->assignableRoles(),
            'channels' => $guild->channels,
            'quotes_channel_id' => $guild->quotes_channel_id,
            'events_channel_id' => $guild->events_channel_id,
            'watched_channel_ids' => $guild->watched_channel_ids,
            'publish_watched_quotes' => $guild->publish_watched_quotes,
            'member_count' => $guild->member_count ?? $guild->members()->where('in_guild', true)->count(),
        ];
    }
}
