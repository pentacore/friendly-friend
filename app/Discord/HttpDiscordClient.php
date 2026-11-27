<?php

declare(strict_types=1);

namespace App\Discord;

use App\Discord\Data\GuildData;
use App\Discord\Data\GuildMemberData;
use App\Discord\Data\SentMessage;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * DiscordClient over Discord's REST API with the bot token.
 */
final readonly class HttpDiscordClient implements DiscordClient
{
    private const int PAGE_SIZE = 1000;

    public function configured(): bool
    {
        return config()->string('discord.bot_token') !== '';
    }

    public function tokenIsValid(): bool
    {
        $response = Http::baseUrl(config()->string('discord.api_base'))
            ->withToken(config()->string('discord.bot_token'), 'Bot')
            ->acceptJson()
            ->get('users/@me');

        if ($response->unauthorized()) {
            return false;
        }

        $response->throw();

        return true;
    }

    /**
     * Every member of the guild, paging with Discord's "after" cursor.
     *
     * @return Generator<int, GuildMemberData>
     */
    public function guildMembers(string $guildId): Generator
    {
        $after = '0';

        do {
            $page = $this->request()
                ->get("guilds/{$guildId}/members", ['limit' => self::PAGE_SIZE, 'after' => $after])
                ->throw()
                ->json();

            $rows = is_array($page) ? array_values(array_filter($page, is_array(...))) : [];

            foreach ($rows as $row) {
                $member = GuildMemberData::fromPayload($row);
                $after = $member->discordId;

                yield $member;
            }
        } while (count($rows) === self::PAGE_SIZE);
    }

    public function guild(string $guildId): GuildData
    {
        $payload = $this->request()->get("guilds/{$guildId}")->throw()->json();

        return GuildData::fromPayload(is_array($payload) ? $payload : []);
    }

    public function registerGuildCommands(string $guildId, array $commands): void
    {
        $this->request()
            ->put("applications/{$this->applicationId()}/guilds/{$guildId}/commands", $commands)
            ->throw();
    }

    public function editInteractionResponse(string $interactionToken, array $message): void
    {
        // Interaction webhooks are authorised by the token in the URL.
        Http::baseUrl(config()->string('discord.api_base'))
            ->acceptJson()
            ->patch("webhooks/{$this->applicationId()}/{$interactionToken}/messages/@original", $this->withoutPings($message))
            ->throw();
    }

    public function sendMessage(string $channelId, array $message): string
    {
        $sent = $this->request()
            ->post("channels/{$channelId}/messages", $this->withoutPings($message))
            ->throw()
            ->json('id');

        return is_string($sent) ? $sent : '';
    }

    public function editMessage(string $channelId, string $messageId, array $message): void
    {
        $this->request()
            ->patch("channels/{$channelId}/messages/{$messageId}", $this->withoutPings($message))
            ->throw();
    }

    public function addReaction(string $channelId, string $messageId, string $emoji): void
    {
        $this->request()
            ->put("channels/{$channelId}/messages/{$messageId}/reactions/".rawurlencode($emoji).'/@me')
            ->throw();
    }

    public function saveScheduledEvent(string $guildId, ?string $scheduledEventId, array $scheduledEvent): string
    {
        $response = $scheduledEventId === null
            ? $this->request()->post("guilds/{$guildId}/scheduled-events", $scheduledEvent)
            : $this->request()->patch("guilds/{$guildId}/scheduled-events/{$scheduledEventId}", $scheduledEvent);

        $id = $response->throw()->json('id');

        return is_string($id) ? $id : '';
    }

    public function channelMessages(string $channelId, ?string $before = null, int $limit = 100): array
    {
        $messages = $this->request()
            ->get("channels/{$channelId}/messages", array_filter(['limit' => $limit, 'before' => $before]))
            ->throw()
            ->json();

        return is_array($messages) ? array_values(array_filter($messages, is_array(...))) : [];
    }

    public function sendDirectMessage(string $userDiscordId, array $message): ?SentMessage
    {
        $channelId = $this->request()
            ->post('users/@me/channels', ['recipient_id' => $userDiscordId])
            ->throw()
            ->json('id');

        if (! is_string($channelId)) {
            return null;
        }

        $response = $this->request()->post("channels/{$channelId}/messages", $this->withoutPings($message));

        // 50007: Cannot send messages to this user (DMs closed).
        if ($response->forbidden()) {
            return null;
        }

        $messageId = $response->throw()->json('id');

        return new SentMessage($channelId, is_string($messageId) ? $messageId : '');
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function withoutPings(array $message): array
    {
        return ['allowed_mentions' => ['parse' => []], ...$message];
    }

    private function applicationId(): string
    {
        return config()->string('services.discord.client_id');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config()->string('discord.api_base'))
            ->withToken(config()->string('discord.bot_token'), 'Bot')
            ->acceptJson()
            // Only transient failures are worth retrying: never a 4xx such
            // as "DMs closed" or "missing permission".
            ->retry(3, 1000, fn (Throwable $e): bool => $e instanceof ConnectionException
                || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())), throw: false);
    }
}
