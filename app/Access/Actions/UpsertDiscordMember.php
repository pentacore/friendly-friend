<?php

declare(strict_types=1);

namespace App\Access\Actions;

use App\Discord\Data\GuildMemberData;
use App\Models\DiscordMember;

/**
 * Creates or refreshes the stored copy of a guild member.
 */
final readonly class UpsertDiscordMember
{
    public function handle(GuildMemberData $data): DiscordMember
    {
        return DiscordMember::query()->updateOrCreate(
            ['discord_id' => $data->discordId],
            [
                'username' => $data->username,
                'global_name' => $data->globalName,
                'guild_nick' => $data->nick,
                'avatar' => $data->avatar,
                'guild_avatar' => $data->guildAvatar,
                'role_ids' => $data->roleIds,
                'in_guild' => true,
            ],
        );
    }
}
