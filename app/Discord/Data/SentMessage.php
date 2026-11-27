<?php

declare(strict_types=1);

namespace App\Discord\Data;

/**
 * Where a message the bot sent landed, so it can be edited later.
 */
final readonly class SentMessage
{
    public function __construct(
        public string $channelId,
        public string $messageId,
    ) {}
}
