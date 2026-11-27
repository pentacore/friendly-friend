<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Events\QuoteScoreChanged;
use App\Models\Quote;
use App\Models\QuoteVote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A 👍 (+1) or 👎 (-1). Voting the same way again removes the vote. The
 * cached score is recomputed from the votes and pushed live. Returns the
 * user's vote afterwards (0 = none).
 */
final readonly class RecordVote
{
    public function handle(Quote $quote, User $user, int $value): int
    {
        $myVote = DB::transaction(function () use ($quote, $user, $value): int {
            $existing = QuoteVote::query()->whereBelongsTo($quote)->where('user_id', $user->id)->lockForUpdate()->first();

            if ($existing !== null && $existing->value === $value) {
                $existing->delete();
                $myVote = 0;
            } else {
                QuoteVote::query()->updateOrCreate(['quote_id' => $quote->id, 'user_id' => $user->id], ['value' => $value]);
                $myVote = $value;
            }

            $quote->forceFill(['score' => (int) $quote->votes()->sum('value')])->saveQuietly();

            return $myVote;
        });

        broadcast(new QuoteScoreChanged($quote->id, $quote->guild_id, $quote->score));

        return $myVote;
    }
}
