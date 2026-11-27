<?php

declare(strict_types=1);

namespace App\Discord\Testing;

use App\Discord\Data\GuildData;
use App\Discord\Data\GuildMemberData;
use App\Discord\DiscordClient;
use PHPUnit\Framework\Assert;

/**
 * In-memory DiscordClient for tests: `app()->instance(DiscordClient::class, $fake = new FakeDiscordClient)`.
 */
final class FakeDiscordClient implements DiscordClient
{
    /** @var list<GuildMemberData> */
    private array $members = [];

    /** @var array<string, GuildData> */
    private array $guilds = [];

    /** @var list<array{channel_id: string, content: string}> */
    private array $sent = [];

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

    public function guild(string $guildId): GuildData
    {
        return $this->guilds[$guildId] ?? new GuildData($guildId, "Guild {$guildId}", null, []);
    }

    public function configured(): bool
    {
        return true;
    }

    public function guildMembers(string $guildId): iterable
    {
        return $this->members;
    }

    public function sendMessage(string $channelId, string $content): void
    {
        $this->sent[] = ['channel_id' => $channelId, 'content' => $content];
    }

    /**
     * @return list<array{channel_id: string, content: string}>
     */
    public function sentMessages(): array
    {
        return $this->sent;
    }

    public function assertSent(string $channelId, string $contains): void
    {
        $matching = array_filter(
            $this->sent,
            fn (array $message): bool => $message['channel_id'] === $channelId && str_contains($message['content'], $contains),
        );

        Assert::assertNotEmpty($matching, "No message to channel [{$channelId}] containing [{$contains}] was sent.");
    }
}
