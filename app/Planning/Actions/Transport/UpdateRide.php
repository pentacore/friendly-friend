<?php

declare(strict_types=1);

namespace App\Planning\Actions\Transport;

use App\Enums\RideDirection;
use App\Events\EventChanged;
use App\Models\EventRide;
use App\Planning\Exceptions\RideFullException;
use Carbon\CarbonImmutable;

/**
 * Changes a ride (driver or organisers). Seats can't drop below the people
 * already in it.
 *
 * @throws RideFullException
 */
final readonly class UpdateRide
{
    public function handle(EventRide $ride, RideDirection $direction, int $seats, ?string $departsFrom, ?CarbonImmutable $departsAt, ?string $note): EventRide
    {
        if ($seats < $ride->passengers()->count()) {
            throw new RideFullException;
        }

        $ride->update([
            'direction' => $direction,
            'seats' => $seats,
            'departs_from' => $departsFrom,
            'departs_at' => $departsAt,
            'note' => $note,
        ]);

        broadcast(new EventChanged($ride->event_id, EventChanged::TRANSPORT));

        return $ride;
    }
}
