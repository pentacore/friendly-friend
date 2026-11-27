<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\StatVoiceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatVoiceSession>
 */
class StatVoiceSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guild_id' => Guild::factory(),
            'discord_user_id' => DiscordUser::factory(),
            'channel_id' => '700',
            'started_at' => now()->subHour(),
            'ended_at' => null,
        ];
    }
}
