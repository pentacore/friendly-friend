<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Models\Quote;
use App\Quotes\Data\NormalizedQuote;

/**
 * Applies the edit form (or a re-run of the AI) to a draft.
 */
final readonly class UpdateDraft
{
    public function __construct(private WriteDraftLines $writeLines) {}

    public function handle(Quote $quote, NormalizedQuote $content): Quote
    {
        $this->writeLines->handle($quote, $content);
        $quote->touch();

        return $quote;
    }
}
