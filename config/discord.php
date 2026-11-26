<?php

declare(strict_types=1);

$ids = static fn (string $key): array => array_values(array_filter(
    array_map(trim(...), explode(',', (string) env($key, ''))),
    static fn (string $id): bool => $id !== '',
));

return [

    /*
    |--------------------------------------------------------------------------
    | Guild (server)
    |--------------------------------------------------------------------------
    |
    | The single Discord server this app belongs to. Only its members can log
    | in. Snowflake IDs are kept as strings (they exceed JS safe integers).
    |
    */

    'guild_id' => (string) env('DISCORD_GUILD_ID', ''),

    /*
    | Role IDs that may log in. Empty list = every member of the guild.
    | Admin roles are always allowed as well.
    */

    'allowed_role_ids' => $ids('DISCORD_ALLOWED_ROLE_IDS'),

    'admin_role_ids' => $ids('DISCORD_ADMIN_ROLE_IDS'),

    /*
    |--------------------------------------------------------------------------
    | Bot
    |--------------------------------------------------------------------------
    */

    'bot_token' => (string) env('DISCORD_BOT_TOKEN', ''),

    'api_base' => 'https://discord.com/api/v10',

    'cdn_base' => 'https://cdn.discordapp.com',

];
