<?php

declare(strict_types=1);

namespace App\Planning\Actions\Transport;

use App\Events\EventChanged;
use App\Models\EventRide;

/**
 * The driver (or an organiser) takes a ride back; its passengers are freed.
 */
final readonly class CancelRide
{
    public function handle(EventRide $ride): void
    {
        $ride->delete();

        broadcast(new EventChanged($ride->event_id, EventChanged::TRANSPORT));
    }
}
