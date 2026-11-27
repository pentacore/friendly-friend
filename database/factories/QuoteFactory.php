<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuoteSource;
use App\Enums\QuoteStatus;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guild_id' => Guild::factory(),
            'status' => QuoteStatus::Published,
            'source' => QuoteSource::SlashCommand,
            'context' => null,
            'said_at' => null,
            'submitted_by_discord_user_id' => DiscordUser::factory(),
            'raw_input' => fake()->sentence(),
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => QuoteStatus::Draft, 'published_at' => null]);
    }

    public function withLine(string $speaker = 'Martin', string $text = 'Hello there'): static
    {
        return $this->afterCreating(function (Quote $quote) use ($speaker, $text): void {
            QuoteLine::factory()->for($quote)->create([
                'position' => $quote->lines()->count(),
                'speaker_name' => $speaker,
                'text' => $text,
            ]);
        });
    }
}
