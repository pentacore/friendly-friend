<?php

declare(strict_types=1);

namespace App\Discord\Gateway;

use App\Discord\Interactions\InteractionAcknowledger;
use Discord\Discord;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Acknowledges interactions from inside the bot's event loop with
 * DiscordPHP's asynchronous HTTP client, so the gateway never blocks.
 */
final readonly class DiscordPhpAcknowledger implements InteractionAcknowledger
{
    public function __construct(private Discord $discord) {}

    public function acknowledge(string $interactionId, string $interactionToken, array $body): void
    {
        $this->discord->getHttpClient()
            ->post("interactions/{$interactionId}/{$interactionToken}/callback", $body)
            ->then(null, function (Throwable $e) use ($interactionId): void {
                Log::warning('Failed to acknowledge a Discord interaction.', ['interaction' => $interactionId, 'exception' => $e->getMessage()]);
            });
    }
}
