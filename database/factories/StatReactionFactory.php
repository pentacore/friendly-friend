<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\StatReaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatReaction>
 */
class StatReactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guild_id' => Guild::factory(),
            'reactor_discord_user_id' => DiscordUser::factory(),
            'target_discord_user_id' => null,
            'emoji' => '😂',
            'day' => today(),
            'count' => 1,
        ];
    }
}
