<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Guild;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guild>
 */
class GuildFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discord_id' => (string) fake()->unique()->numberBetween(100_000_000_000_000_000, 999_999_999_999_999_999),
            'name' => fake()->words(2, true),
            'icon' => null,
            'bot_present' => true,
            'enabled' => true,
            'allow_all_members' => false,
            'allowed_role_ids' => ['100'],
            'admin_role_ids' => ['900'],
            'roles' => [
                ['id' => '100', 'name' => 'Friends', 'color' => 0, 'position' => 1, 'managed' => false],
                ['id' => '900', 'name' => 'Admin', 'color' => 0, 'position' => 2, 'managed' => false],
            ],
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }

    public function allowAllMembers(): static
    {
        return $this->state(fn (): array => ['allow_all_members' => true]);
    }
}
