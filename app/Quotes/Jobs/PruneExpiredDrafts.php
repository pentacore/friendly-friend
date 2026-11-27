<?php

declare(strict_types=1);

namespace App\Quotes\Jobs;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Deletes drafts nobody saved within config('quotes.draft_ttl_minutes').
 * Their buttons then answer "expired".
 */
final class PruneExpiredDrafts implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Quote::query()
            ->where('status', QuoteStatus::Draft)
            ->where('updated_at', '<', now()->subMinutes(config()->integer('quotes.draft_ttl_minutes')))
            ->delete();
    }
}
