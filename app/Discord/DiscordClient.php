<?php

declare(strict_types=1);

namespace App\Discord;

use App\Discord\Data\GuildData;
use App\Discord\Data\GuildMemberData;

/**
 * Discord REST calls made as the bot. Everything outside App\Discord talks to
 * Discord through this interface; tests swap in Testing\FakeDiscordClient.
 *
 * Message payloads are Discord "message" objects (content, components…).
 * Implementations add `allowed_mentions: none` so nothing ever pings people.
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

    /**
     * Replace the bot's slash and context-menu commands in a guild.
     *
     * @param  list<array<string, mixed>>  $commands
     */
    public function registerGuildCommands(string $guildId, array $commands): void;

    /**
     * Edit the response to an interaction the bot already acknowledged.
     *
     * @param  array<string, mixed>  $message
     */
    public function editInteractionResponse(string $interactionToken, array $message): void;

    /**
     * Post a message; returns its ID.
     *
     * @param  array<string, mixed>  $message
     */
    public function sendMessage(string $channelId, array $message): string;

    /**
     * Replace a message the bot posted.
     *
     * @param  array<string, mixed>  $message
     */
    public function editMessage(string $channelId, string $messageId, array $message): void;

    /**
     * React to a message as the bot (a unicode emoji such as "📝").
     */
    public function addReaction(string $channelId, string $messageId, string $emoji): void;

    /**
     * Create (no ID) or update a native guild scheduled event; returns its ID.
     *
     * @param  array<string, mixed>  $scheduledEvent
     */
    public function saveScheduledEvent(string $guildId, ?string $scheduledEventId, array $scheduledEvent): string;

    /**
     * Up to 100 messages of a channel, newest first, older than $before.
     *
     * @return list<array<mixed>> Discord message objects
     */
    public function channelMessages(string $channelId, ?string $before = null, int $limit = 100): array;

    /**
     * DM a user. Returns false when Discord refuses (DMs closed, no shared server).
     *
     * @param  array<string, mixed>  $message
     */
    public function sendDirectMessage(string $userDiscordId, array $message): bool;
}
