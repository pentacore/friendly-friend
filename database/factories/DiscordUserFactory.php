<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordUser>
 */
class DiscordUserFactory extends Factory
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
            'avatar' => null,
        ];
    }
}
