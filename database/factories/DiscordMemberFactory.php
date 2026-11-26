<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordMember>
 */
class DiscordMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discord_id' => (string) fake()->unique()->numberBetween(100_000_000_000_000_000, 999_999_999_999_999_999),
            'username' => fake()->unique()->userName(),
            'global_name' => fake()->firstName(),
            'guild_nick' => null,
            'avatar' => null,
            'guild_avatar' => null,
            'role_ids' => [],
            'in_guild' => true,
        ];
    }

    /**
     * @param  list<string>  $roleIds
     */
    public function withRoles(array $roleIds): static
    {
        return $this->state(fn (): array => ['role_ids' => $roleIds]);
    }

    public function leftGuild(): static
    {
        return $this->state(fn (): array => ['in_guild' => false]);
    }
}
