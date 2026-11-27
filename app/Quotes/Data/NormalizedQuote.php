<?php

declare(strict_types=1);

namespace App\Quotes\Data;

use App\Enums\SaidAtPrecision;
use Carbon\CarbonImmutable;

/**
 * The cleaned-up content of a quote: lines, optional context and when it was
 * said. Produced by a QuoteNormalizer (AI or plain parsing) or the edit form.
 */
final readonly class NormalizedQuote
{
    /**
     * @param  list<NormalizedLine>  $lines
     * @param  array<string, mixed>|null  $aiOutput  raw AI answer, kept for debugging
     */
    public function __construct(
        public array $lines,
        public ?string $context = null,
        public ?CarbonImmutable $saidAt = null,
        public ?SaidAtPrecision $saidAtPrecision = null,
        public ?array $aiOutput = null,
    ) {}
}
