<?php

declare(strict_types=1);

namespace App\Access\Actions;

use App\Discord\Data\DiscordUserData;
use App\Models\DiscordUser;

/**
 * Creates or refreshes the stored Discord account (username, avatar…).
 */
final readonly class RecordDiscordUser
{
    public function handle(DiscordUserData $data): DiscordUser
    {
        return DiscordUser::query()->updateOrCreate(
            ['discord_id' => $data->discordId],
            [
                'username' => $data->username,
                'global_name' => $data->globalName,
                'avatar' => $data->avatar,
            ],
        );
    }
}
