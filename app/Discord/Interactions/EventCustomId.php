<?php

declare(strict_types=1);

namespace App\Discord\Interactions;

use App\Enums\RsvpStatus;

/**
 * Component custom IDs on event messages:
 *  - "event:rsvp:<id>:<status>": an RSVP button on the announcement
 *  - "event:dates:<id>": "Vote on dates" (opens the private date picker)
 *  - "event:yes:<id>" / "event:maybe:<id>": the picker's two select menus
 */
final readonly class EventCustomId
{
    public const string RSVP = 'rsvp';

    public const string DATES = 'dates';

    public const string YES = 'yes';

    public const string MAYBE = 'maybe';

    private function __construct(
        public string $action,
        public int $eventId,
        public ?RsvpStatus $status = null,
    ) {}

    public static function rsvp(int $eventId, RsvpStatus $status): string
    {
        return "event:rsvp:{$eventId}:{$status->value}";
    }

    public static function dates(int $eventId): string
    {
        return "event:dates:{$eventId}";
    }

    public static function datesYes(int $eventId): string
    {
        return "event:yes:{$eventId}";
    }

    public static function datesMaybe(int $eventId): string
    {
        return "event:maybe:{$eventId}";
    }

    public static function parse(mixed $customId): ?self
    {
        if (! is_string($customId)) {
            return null;
        }

        if (preg_match('/^event:rsvp:(\d+):(going|maybe|declined)$/', $customId, $match) === 1) {
            return new self(self::RSVP, (int) $match[1], RsvpStatus::from($match[2]));
        }

        if (preg_match('/^event:(dates|yes|maybe):(\d+)$/', $customId, $match) === 1) {
            return new self($match[1], (int) $match[2]);
        }

        return null;
    }

    /**
     * A select menu of the date picker (answered by updating the picker in place).
     */
    public function isDateSelect(): bool
    {
        return $this->action === self::YES || $this->action === self::MAYBE;
    }
}
