<?php

declare(strict_types=1);

namespace App\Discord\Interactions;

use App\Enums\RsvpStatus;

/**
 * RSVP button custom IDs on event announcements: "event:rsvp:<event id>:<status>".
 */
final readonly class EventCustomId
{
    private function __construct(
        public int $eventId,
        public RsvpStatus $status,
    ) {}

    public static function rsvp(int $eventId, RsvpStatus $status): string
    {
        return "event:rsvp:{$eventId}:{$status->value}";
    }

    public static function parse(mixed $customId): ?self
    {
        if (! is_string($customId) || preg_match('/^event:rsvp:(\d+):(going|maybe|declined)$/', $customId, $match) !== 1) {
            return null;
        }

        return new self((int) $match[1], RsvpStatus::from($match[2]));
    }
}
