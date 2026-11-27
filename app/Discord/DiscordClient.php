<?php

declare(strict_types=1);

namespace App\Discord;

use App\Discord\Data\GuildData;
use App\Discord\Data\GuildMemberData;

/**
 * Discord REST calls made as the bot. Everything outside App\Discord talks to
 * Discord through this interface; tests swap in Testing\FakeDiscordClient.
 */
interface DiscordClient
{
    /**
     * Whether a bot token is configured.
     */
    public function configured(): bool;

    /**
     * Whether Discord accepts the bot token (false on 401).
     */
    public function tokenIsValid(): bool;

    /**
     * Every member of the guild.
     *
     * @return iterable<int, GuildMemberData>
     */
    public function guildMembers(string $guildId): iterable;

    /**
     * The guild with its roles.
     */
    public function guild(string $guildId): GuildData;

    public function sendMessage(string $channelId, string $content): void;
}
