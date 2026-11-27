<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordUser;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->firstName();

        return [
            'display_name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999_999),
            'discord_user_id' => null,
        ];
    }

    public function forDiscordUser(DiscordUser $user): static
    {
        return $this->state(fn (): array => ['discord_user_id' => $user->id, 'display_name' => $user->display_name]);
    }
}
