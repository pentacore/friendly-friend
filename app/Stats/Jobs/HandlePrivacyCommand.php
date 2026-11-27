<?php

declare(strict_types=1);

namespace App\Stats\Jobs;

use App\Stats\Discord\PrivacyInteractions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * Does the work for a `/privacy` command the bot already acknowledged.
 */
#[Tries(1)]
final class HandlePrivacyCommand implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<mixed>  $payload  INTERACTION_CREATE
     */
    public function __construct(public readonly array $payload) {}

    public function handle(PrivacyInteractions $interactions): void
    {
        $interactions->handle($this->payload);
    }
}
