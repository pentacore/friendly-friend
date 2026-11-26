<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discord_member_id' => DiscordMember::factory()->withRoles(['100']),
            'remember_token' => Str::random(10),
        ];
    }

    public function forMember(DiscordMember $member): static
    {
        return $this->state(fn (): array => ['discord_member_id' => $member->id]);
    }
}
