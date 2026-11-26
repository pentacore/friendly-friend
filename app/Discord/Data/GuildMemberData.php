<?php

declare(strict_types=1);

namespace App\Discord\Data;

/**
 * A guild member as returned by Discord's guild member endpoints.
 */
final readonly class GuildMemberData
{
    /**
     * @param  list<string>  $roleIds
     */
    public function __construct(
        public string $discordId,
        public string $username,
        public ?string $globalName,
        public ?string $nick,
        public ?string $avatar,
        public ?string $guildAvatar,
        public array $roleIds,
    ) {}

    /**
     * @param  array<mixed>  $payload  Discord "Guild Member" object
     */
    public static function fromPayload(array $payload): self
    {
        $user = is_array($payload['user'] ?? null) ? $payload['user'] : [];
        $roles = is_array($payload['roles'] ?? null) ? $payload['roles'] : [];

        return new self(
            discordId: self::string($user, 'id') ?? '',
            username: self::string($user, 'username') ?? '',
            globalName: self::string($user, 'global_name'),
            nick: self::string($payload, 'nick'),
            avatar: self::string($user, 'avatar'),
            guildAvatar: self::string($payload, 'avatar'),
            roleIds: array_values(array_filter($roles, is_string(...))),
        );
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
