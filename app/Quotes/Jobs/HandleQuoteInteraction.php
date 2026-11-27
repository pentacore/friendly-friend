<?php

declare(strict_types=1);

namespace App\Quotes\Jobs;

use App\Quotes\Discord\QuoteInteractions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * Does the work for an interaction the bot already acknowledged. Not
 * retried: the interaction token only lives 15 minutes and a half-done
 * capture is worse than a clear failure message.
 */
#[Tries(1)]
final class HandleQuoteInteraction implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<mixed>  $payload  INTERACTION_CREATE
     */
    public function __construct(public readonly array $payload) {}

    public function handle(QuoteInteractions $interactions): void
    {
        $interactions->handle($this->payload);
    }
}
