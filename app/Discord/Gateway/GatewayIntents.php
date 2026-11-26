<?php

declare(strict_types=1);

namespace App\Discord\Gateway;

use Discord\WebSockets\Intents;

/**
 * The gateway intents the bot requests (spec §8). GUILD_MEMBERS,
 * GUILD_PRESENCES and MESSAGE_CONTENT are privileged: turn them on in the
 * Discord developer portal (see docs/discord-app.md).
 */
final class GatewayIntents
{
    public static function value(): int
    {
        return Intents::GUILDS
            | Intents::GUILD_MEMBERS
            | Intents::GUILD_MESSAGES
            | Intents::MESSAGE_CONTENT
            | Intents::GUILD_MESSAGE_REACTIONS
            | Intents::GUILD_VOICE_STATES
            | Intents::GUILD_PRESENCES;
    }
}
