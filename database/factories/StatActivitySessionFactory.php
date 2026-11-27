<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\StatActivitySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatActivitySession>
 */
class StatActivitySessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guild_id' => Guild::factory(),
            'discord_user_id' => DiscordUser::factory(),
            'activity_name' => 'Deep Rock Galactic',
            'activity_type' => 0,
            'started_at' => now()->subHour(),
            'ended_at' => null,
        ];
    }
}
