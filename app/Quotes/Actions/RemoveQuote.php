<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Enums\QuoteStatus;
use App\Models\Quote;

/**
 * Takes a quote off the site (admins). Kept as `removed`, not deleted, so it
 * can be restored and the source message can't be quoted again by accident.
 */
final readonly class RemoveQuote
{
    public function handle(Quote $quote): void
    {
        $quote->update(['status' => QuoteStatus::Removed]);
    }
}
