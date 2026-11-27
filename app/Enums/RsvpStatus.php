<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Someone's answer to an event.
 */
enum RsvpStatus: string
{
    case Going = 'going';
    case Maybe = 'maybe';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Going => 'Going',
            self::Maybe => 'Maybe',
            self::Declined => "Can't",
        };
    }
}
