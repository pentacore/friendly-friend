<?php

declare(strict_types=1);

namespace App\Discord\Interactions;

use Illuminate\Support\Facades\Http;

final readonly class HttpInteractionAcknowledger implements InteractionAcknowledger
{
    public function acknowledge(string $interactionId, string $interactionToken, array $body): void
    {
        Http::baseUrl(config()->string('discord.api_base'))
            ->acceptJson()
            ->post("interactions/{$interactionId}/{$interactionToken}/callback", $body)
            ->throw();
    }
}
