<?php

declare(strict_types=1);

namespace App\Quotes\Data;

/**
 * The Discord message a quote was taken from.
 */
final readonly class SourceMessage
{
    public function __construct(
        public string $messageId,
        public string $channelId,
        public ?string $url,
    ) {}
}
