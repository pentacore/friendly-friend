<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\GuildMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuildMember>
 */
class GuildMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guild_id' => Guild::factory(),
            'discord_user_id' => DiscordUser::factory(),
            'nick' => null,
            'guild_avatar' => null,
            'role_ids' => ['100'],
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
