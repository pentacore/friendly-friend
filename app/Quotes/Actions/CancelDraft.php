<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Enums\QuoteStatus;
use App\Models\Quote;

final readonly class CancelDraft
{
    public function handle(Quote $quote): void
    {
        if ($quote->status === QuoteStatus::Draft) {
            $quote->delete();
        }
    }
}
