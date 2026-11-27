<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Enums\QuoteStatus;
use App\Models\Quote;

/**
 * Puts a removed quote back on the site (admins), with its votes, Elo and
 * history as they were.
 */
final readonly class RestoreQuote
{
    public function handle(Quote $quote): void
    {
        $quote->update(['status' => QuoteStatus::Published, 'published_at' => $quote->published_at ?? now()]);
    }
}
