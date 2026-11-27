<?php

declare(strict_types=1);

namespace App\Discord\Data;

/**
 * A guild (server) as sent in GUILD_CREATE / GUILD_UPDATE or GET /guilds/{id}.
 */
final readonly class GuildData
{
    /**
     * @param  list<GuildRoleData>  $roles
     */
    public function __construct(
        public string $discordId,
        public string $name,
        public ?string $icon,
        public array $roles,
    ) {}

    /**
     * @param  array<mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $roles = is_array($payload['roles'] ?? null) ? $payload['roles'] : [];

        return new self(
            discordId: is_string($payload['id'] ?? null) ? $payload['id'] : '',
            name: is_string($payload['name'] ?? null) ? $payload['name'] : '',
            icon: is_string($payload['icon'] ?? null) ? $payload['icon'] : null,
            roles: array_map(
                GuildRoleData::fromPayload(...),
                array_values(array_filter($roles, is_array(...))),
            ),
        );
    }

    public function isValid(): bool
    {
        return $this->discordId !== '' && $this->name !== '';
    }
}
