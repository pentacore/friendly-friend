<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Models\Quote;
use App\Models\QuoteMatchup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A this-or-that pick. Updates both quotes' Elo ratings (K = 32) and play
 * counts and records the matchup.
 */
final readonly class RecordMatchup
{
    private const int K = 32;

    public function handle(User $user, Quote $quoteA, Quote $quoteB, Quote $winner): void
    {
        DB::transaction(function () use ($user, $quoteA, $quoteB, $winner): void {
            $a = Quote::query()->lockForUpdate()->findOrFail($quoteA->id);
            $b = Quote::query()->lockForUpdate()->findOrFail($quoteB->id);

            $expectedA = 1 / (1 + 10 ** (($b->elo - $a->elo) / 400));
            $scoreA = $winner->id === $a->id ? 1.0 : 0.0;

            $a->forceFill([
                'elo' => (int) round($a->elo + self::K * ($scoreA - $expectedA)),
                'matchup_count' => $a->matchup_count + 1,
            ])->saveQuietly();

            $b->forceFill([
                'elo' => (int) round($b->elo + self::K * ((1 - $scoreA) - (1 - $expectedA))),
                'matchup_count' => $b->matchup_count + 1,
            ])->saveQuietly();

            QuoteMatchup::query()->create([
                'user_id' => $user->id,
                'quote_a_id' => $a->id,
                'quote_b_id' => $b->id,
                'winner_quote_id' => $winner->id,
            ]);
        });
    }
}
