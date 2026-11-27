<?php

declare(strict_types=1);

namespace App\Discord\Testing;

use App\Discord\Interactions\InteractionAcknowledger;

/**
 * Records interaction acknowledgements instead of sending them (tests).
 */
final class FakeInteractionAcknowledger implements InteractionAcknowledger
{
    /** @var list<array{id: string, token: string, body: array<string, mixed>}> */
    public array $acknowledged = [];

    public function acknowledge(string $interactionId, string $interactionToken, array $body): void
    {
        $this->acknowledged[] = ['id' => $interactionId, 'token' => $interactionToken, 'body' => $body];
    }
}
