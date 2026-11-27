<?php

declare(strict_types=1);

namespace App\Planning\Jobs;

use App\Planning\Discord\EventInteractions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * Does the work for an RSVP button the bot already acknowledged.
 */
#[Tries(1)]
final class HandleEventInteraction implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<mixed>  $payload  INTERACTION_CREATE
     */
    public function __construct(public readonly array $payload) {}

    public function handle(EventInteractions $interactions): void
    {
        $interactions->handle($this->payload);
    }
}
