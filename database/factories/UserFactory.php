<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\GuildMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * A bare user has no guild membership and therefore no access; use
     * member() or memberOf() for a user who passes `guild.access`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discord_user_id' => DiscordUser::factory(),
            'remember_token' => Str::random(10),
        ];
    }

    public function forDiscordUser(DiscordUser $discordUser): static
    {
        return $this->state(fn (): array => ['discord_user_id' => $discordUser->id]);
    }

    /**
     * Member of a new enabled guild with the allowed role ('100').
     */
    public function member(): static
    {
        return $this->afterCreating(function (User $user): void {
            GuildMember::factory()->for($user->discordUser)->create();
        });
    }

    /**
     * Member of the given guild with the given roles.
     *
     * @param  list<string>  $roleIds
     */
    public function memberOf(Guild $guild, array $roleIds = ['100']): static
    {
        return $this->afterCreating(function (User $user) use ($guild, $roleIds): void {
            GuildMember::factory()->for($guild)->for($user->discordUser)->withRoles($roleIds)->create();
        });
    }
}
