<?php

declare(strict_types=1);

namespace App\Discord\Interactions;

/**
 * Sends the immediate interaction callback. In the bot process this is
 * DiscordPHP's non-blocking HTTP client (Gateway\DiscordPhpAcknowledger);
 * elsewhere a plain HTTP call; in tests a fake.
 */
interface InteractionAcknowledger
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function acknowledge(string $interactionId, string $interactionToken, array $body): void;
}
