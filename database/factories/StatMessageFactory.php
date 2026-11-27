<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\StatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatMessage>
 */
class StatMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guild_id' => Guild::factory(),
            'discord_user_id' => DiscordUser::factory(),
            'channel_id' => (string) fake()->numberBetween(100_000_000_000_000_000, 999_999_999_999_999_999),
            'hour' => now()->startOfHour(),
            'count' => 1,
        ];
    }
}
