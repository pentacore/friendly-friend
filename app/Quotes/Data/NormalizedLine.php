<?php

declare(strict_types=1);

namespace App\Quotes\Data;

/**
 * One line of a quote as understood from the input: who said what. The
 * Discord ID is set when the speaker is known for certain (a message author
 * or a mention).
 */
final readonly class NormalizedLine
{
    public function __construct(
        public string $speakerName,
        public string $text,
        public ?string $speakerDiscordId = null,
    ) {}
}
