<?php

declare(strict_types=1);

namespace App\Discord\Data;

/**
 * A role of a guild, as cached on `guilds.roles` for the role pickers.
 */
final readonly class GuildRoleData
{
    public function __construct(
        public string $id,
        public string $name,
        public int $color,
        public int $position,
        public bool $managed,
    ) {}

    /**
     * @param  array<mixed>  $payload  Discord "Role" object
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            id: is_string($payload['id'] ?? null) ? $payload['id'] : '',
            name: is_string($payload['name'] ?? null) ? $payload['name'] : '',
            color: is_int($payload['color'] ?? null) ? $payload['color'] : 0,
            position: is_int($payload['position'] ?? null) ? $payload['position'] : 0,
            managed: ($payload['managed'] ?? false) === true,
        );
    }

    /**
     * @return array{id: string, name: string, color: int, position: int, managed: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'position' => $this->position,
            'managed' => $this->managed,
        ];
    }
}
