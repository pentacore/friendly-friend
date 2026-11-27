<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether an event is still on.
 */
enum EventStatus: string
{
    case Planned = 'planned';
    /** Kept for the record (and its costs); nobody can RSVP any more. */
    case Cancelled = 'cancelled';
}
