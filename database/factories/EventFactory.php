<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\Guild;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addWeek()->setTime(18, 0);

        return [
            'guild_id' => Guild::factory(),
            'created_by_discord_user_id' => DiscordUser::factory(),
            'title' => 'Crayfish party',
            'description' => null,
            'location' => 'The cabin',
            'location_url' => null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(6),
            'timezone' => 'Europe/Stockholm',
            'status' => EventStatus::Planned,
            'currency' => 'SEK',
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => EventStatus::Cancelled]);
    }

    public function past(): static
    {
        return $this->state(fn (): array => ['starts_at' => now()->subWeek(), 'ends_at' => now()->subWeek()->addHours(4)]);
    }
}
