<?php

declare(strict_types=1);

namespace App\Access\Actions;

use App\Discord\Data\GuildMemberData;
use App\Models\Guild;
use App\Models\GuildMember;

/**
 * Creates or refreshes a member's account and their membership of a guild.
 */
final readonly class RecordGuildMember
{
    public function __construct(private RecordDiscordUser $recordUser) {}

    public function handle(Guild $guild, GuildMemberData $data): GuildMember
    {
        $user = $this->recordUser->handle($data->user());

        return GuildMember::query()->updateOrCreate(
            ['guild_id' => $guild->id, 'discord_user_id' => $user->id],
            [
                'nick' => $data->nick,
                'guild_avatar' => $data->guildAvatar,
                'role_ids' => $data->roleIds,
                'in_guild' => true,
            ],
        );
    }
}
