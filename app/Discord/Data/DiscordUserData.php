<?php

declare(strict_types=1);

namespace App\Discord\Data;

/**
 * A Discord account (the "User" object), independent of any guild.
 */
final readonly class DiscordUserData
{
    public function __construct(
        public string $discordId,
        public string $username,
        public ?string $globalName,
        public ?string $avatar,
    ) {}

    /**
     * @param  array<mixed>  $payload  Discord "User" object
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            discordId: is_string($payload['id'] ?? null) ? $payload['id'] : '',
            username: is_string($payload['username'] ?? null) ? $payload['username'] : '',
            globalName: is_string($payload['global_name'] ?? null) ? $payload['global_name'] : null,
            avatar: is_string($payload['avatar'] ?? null) ? $payload['avatar'] : null,
        );
    }

    public function isValid(): bool
    {
        return $this->discordId !== '' && $this->username !== '';
    }
}
