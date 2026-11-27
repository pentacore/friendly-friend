<?php

declare(strict_types=1);

namespace App\Discord\Testing;

use App\Discord\Data\GuildData;
use App\Discord\Data\GuildMemberData;
use App\Discord\Data\SentMessage;
use App\Discord\DiscordClient;
use PHPUnit\Framework\Assert;
use Throwable;

/**
 * In-memory DiscordClient for tests: `app()->instance(DiscordClient::class, $fake = new FakeDiscordClient)`.
 */
final class FakeDiscordClient implements DiscordClient
{
    /** @var list<GuildMemberData> */
    private array $members = [];

    /** @var array<string, GuildData> */
    private array $guilds = [];

    /** @var list<array{channel_id: string, message: array<string, mixed>}> */
    private array $sent = [];

    /** @var list<array{user_id: string, message: array<string, mixed>}> */
    private array $directMessages = [];

    /** @var list<array{token: string, message: array<string, mixed>}> */
    private array $responses = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $commands = [];

    /** @var array<string, list<array<mixed>>> channel ID → messages, newest first */
    private array $channelMessages = [];

    private ?Throwable $channelMessagesFailure = null;

    /** @var list<string> user IDs whose DMs are closed */
    private array $closedDms = [];

    /** @var list<array{channel_id: string, message_id: string, message: array<string, mixed>}> */
    private array $edits = [];

    /** @var array<string, array<string, mixed>> scheduled event ID → last payload */
    private array $scheduledEvents = [];

    private ?Throwable $scheduledEventsFailure = null;

    /** @var list<array{channel_id: string, message_id: string, emoji: string}> */
    private array $reactions = [];

    /**
     * @param  list<GuildMemberData>  $members
     */
    public function withMembers(array $members): self
    {
        $this->members = $members;

        return $this;
    }

    public function withGuild(GuildData $guild): self
    {
        $this->guilds[$guild->discordId] = $guild;

        return $this;
    }

    /**
     * @param  list<array<mixed>>  $messages  newest first
     */
    public function withChannelMessages(string $channelId, array $messages): self
    {
        $this->channelMessages[$channelId] = $messages;

        return $this;
    }

    public function failChannelMessagesWith(Throwable $failure): self
    {
        $this->channelMessagesFailure = $failure;

        return $this;
    }

    public function withClosedDms(string $userDiscordId): self
    {
        $this->closedDms[] = $userDiscordId;

        return $this;
    }

    public function configured(): bool
    {
        return true;
    }

    public function tokenIsValid(): bool
    {
        return true;
    }

    public function guildMembers(string $guildId): iterable
    {
        return $this->members;
    }

    public function guild(string $guildId): GuildData
    {
        return $this->guilds[$guildId] ?? new GuildData($guildId, "Guild {$guildId}", null, []);
    }

    public function registerGuildCommands(string $guildId, array $commands): void
    {
        $this->commands[$guildId] = $commands;
    }

    public function editInteractionResponse(string $interactionToken, array $message): void
    {
        $this->responses[] = ['token' => $interactionToken, 'message' => $message];
    }

    public function sendMessage(string $channelId, array $message): string
    {
        $this->sent[] = ['channel_id' => $channelId, 'message' => $message];

        return (string) (1000 + count($this->sent));
    }

    public function editMessage(string $channelId, string $messageId, array $message): void
    {
        $this->edits[] = ['channel_id' => $channelId, 'message_id' => $messageId, 'message' => $message];
    }

    public function addReaction(string $channelId, string $messageId, string $emoji): void
    {
        $this->reactions[] = ['channel_id' => $channelId, 'message_id' => $messageId, 'emoji' => $emoji];
    }

    /**
     * @return list<array{channel_id: string, message_id: string, emoji: string}>
     */
    public function reactions(): array
    {
        return $this->reactions;
    }

    public function saveScheduledEvent(string $guildId, ?string $scheduledEventId, array $scheduledEvent): string
    {
        if ($this->scheduledEventsFailure instanceof Throwable) {
            throw $this->scheduledEventsFailure;
        }

        $id = $scheduledEventId ?? (string) (5000 + count($this->scheduledEvents));
        $this->scheduledEvents[$id] = ['guild_id' => $guildId, ...$scheduledEvent];

        return $id;
    }

    /**
     * Make saveScheduledEvent() fail, e.g. a 403 for a missing Manage Events permission.
     */
    public function failScheduledEventsWith(Throwable $failure): self
    {
        $this->scheduledEventsFailure = $failure;

        return $this;
    }

    /**
     * @return list<array{channel_id: string, message_id: string, message: array<string, mixed>}>
     */
    public function editedMessages(): array
    {
        return $this->edits;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function scheduledEvents(): array
    {
        return $this->scheduledEvents;
    }

    public function channelMessages(string $channelId, ?string $before = null, int $limit = 100): array
    {
        if ($this->channelMessagesFailure instanceof Throwable) {
            throw $this->channelMessagesFailure;
        }

        $messages = $this->channelMessages[$channelId] ?? [];

        if ($before !== null) {
            $index = array_search($before, array_column($messages, 'id'), true);
            $messages = $index === false ? [] : array_slice($messages, $index + 1);
        }

        return array_slice($messages, 0, $limit);
    }

    public function sendDirectMessage(string $userDiscordId, array $message): ?SentMessage
    {
        if (in_array($userDiscordId, $this->closedDms, true)) {
            return null;
        }

        $this->directMessages[] = ['user_id' => $userDiscordId, 'message' => $message];

        return new SentMessage("dm-{$userDiscordId}", (string) (2000 + count($this->directMessages)));
    }

    /**
     * @return list<array{channel_id: string, message: array<string, mixed>}>
     */
    public function sentMessages(): array
    {
        return $this->sent;
    }

    /**
     * @return list<array{user_id: string, message: array<string, mixed>}>
     */
    public function directMessages(): array
    {
        return $this->directMessages;
    }

    /**
     * @return list<array{token: string, message: array<string, mixed>}>
     */
    public function interactionResponses(): array
    {
        return $this->responses;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function commandsFor(string $guildId): ?array
    {
        return $this->commands[$guildId] ?? null;
    }

    /**
     * The last response to an interaction, for assertions.
     *
     * @return array<string, mixed>
     */
    public function lastResponse(): array
    {
        $last = array_last($this->responses);

        Assert::assertNotNull($last, 'No interaction response was sent.');

        return $last['message'];
    }

    public function assertSent(string $channelId, string $contains): void
    {
        $matching = array_filter(
            $this->sent,
            fn (array $sent): bool => $sent['channel_id'] === $channelId && str_contains(self::content($sent['message']), $contains),
        );

        Assert::assertNotEmpty($matching, "No message to channel [{$channelId}] containing [{$contains}] was sent.");
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public static function content(array $message): string
    {
        return is_string($message['content'] ?? null) ? $message['content'] : '';
    }
}
