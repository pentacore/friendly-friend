<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which way a ride goes, seen from the event.
 */
enum RideDirection: string
{
    case To = 'to';
    case From = 'from';
    case Both = 'both';

    /**
     * Whether this ride covers the given leg (a "both" ride covers either).
     */
    public function covers(self $leg): bool
    {
        return $this === self::Both || $this === $leg;
    }
}
