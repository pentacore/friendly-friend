<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteLine>
 */
class QuoteLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'position' => 0,
            'person_id' => null,
            'speaker_discord_user_id' => null,
            'speaker_name' => fake()->firstName(),
            'text' => fake()->sentence(),
        ];
    }
}
