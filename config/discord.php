<?php

declare(strict_types=1);

$ids = static fn (string $key): array => array_values(array_filter(
    array_map(trim(...), explode(',', (string) env($key, ''))),
    static fn (string $id): bool => $id !== '',
));

return [

    /*
    |--------------------------------------------------------------------------
    | Owners
    |--------------------------------------------------------------------------
    |
    | Discord *user* IDs (comma-separated) that can always log in, are admins,
    | and are the only ones who can manage servers on /admin/guilds. Which
    | servers count, and which of their roles get in, is stored per server
    | in the `guilds` table, not here.
    |
    */

    'owner_ids' => $ids('DISCORD_OWNER_IDS'),

    /*
    |--------------------------------------------------------------------------
    | Bot
    |--------------------------------------------------------------------------
    */

    'bot_token' => (string) env('DISCORD_BOT_TOKEN', ''),

    'api_base' => 'https://discord.com/api/v10',

    'cdn_base' => 'https://cdn.discordapp.com',

];
