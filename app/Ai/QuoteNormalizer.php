<?php

declare(strict_types=1);

namespace App\Ai;

use App\Quotes\Data\NormalizedQuote;

/**
 * Turns captured input into clean quote lines (who said what), context and
 * when. Implementations must never throw for bad input: capture always works.
 */
interface QuoteNormalizer
{
    public function normalize(CaptureInput $input): NormalizedQuote;
}
