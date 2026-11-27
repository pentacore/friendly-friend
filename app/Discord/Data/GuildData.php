<?php

declare(strict_types=1);

namespace App\Discord\Data;

/**
 * A guild (server) as sent in GUILD_CREATE / GUILD_UPDATE or GET /guilds/{id}.
 */
final readonly class GuildData
{
    /** Discord channel types that hold messages: text and announcement. */
    private const array MESSAGE_CHANNEL_TYPES = [0, 5];

    /**
     * @param  list<GuildRoleData>  $roles
     * @param  list<array{id: string, name: string, position: int}>  $channels  text channels; empty when the payload has none (REST)
     */
    public function __construct(
        public string $discordId,
        public string $name,
        public ?string $icon,
        public array $roles,
        public array $channels = [],
    ) {}

    /**
     * @param  array<mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $roles = is_array($payload['roles'] ?? null) ? $payload['roles'] : [];
        $channels = is_array($payload['channels'] ?? null) ? $payload['channels'] : [];

        return new self(
            discordId: is_string($payload['id'] ?? null) ? $payload['id'] : '',
            name: is_string($payload['name'] ?? null) ? $payload['name'] : '',
            icon: is_string($payload['icon'] ?? null) ? $payload['icon'] : null,
            roles: array_map(
                GuildRoleData::fromPayload(...),
                array_values(array_filter($roles, is_array(...))),
            ),
            channels: self::messageChannels($channels),
        );
    }

    /**
     * @param  array<mixed>  $channels
     * @return list<array{id: string, name: string, position: int}>
     */
    private static function messageChannels(array $channels): array
    {
        $result = [];

        foreach ($channels as $channel) {
            if (! is_array($channel) || ! in_array($channel['type'] ?? null, self::MESSAGE_CHANNEL_TYPES, true)) {
                continue;
            }

            if (is_string($channel['id'] ?? null) && is_string($channel['name'] ?? null)) {
                $result[] = [
                    'id' => $channel['id'],
                    'name' => $channel['name'],
                    'position' => is_int($channel['position'] ?? null) ? $channel['position'] : 0,
                ];
            }
        }

        usort($result, fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return $result;
    }

    public function isValid(): bool
    {
        return $this->discordId !== '' && $this->name !== '';
    }
}
