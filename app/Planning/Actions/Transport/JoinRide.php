<?php

declare(strict_types=1);

namespace App\Planning\Actions\Transport;

use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\EventRide;
use App\Models\EventRidePassenger;
use App\Planning\Exceptions\RideFullException;
use Illuminate\Support\Facades\DB;

/**
 * Takes a seat (or updates your pickup note). Locks the ride so two people
 * can't take the last seat at once.
 */
final readonly class JoinRide
{
    /**
     * @throws RideFullException
     */
    public function handle(EventRide $ride, DiscordUser $member, ?string $pickupNote): void
    {
        DB::transaction(function () use ($ride, $member, $pickupNote): void {
            $locked = EventRide::query()->lockForUpdate()->findOrFail($ride->id);
            $alreadyIn = $locked->passengers()->where('discord_user_id', $member->id)->exists();

            if (! $alreadyIn && ($locked->driver_discord_user_id === $member->id || $locked->passengers()->count() >= $locked->seats)) {
                throw new RideFullException;
            }

            EventRidePassenger::query()->updateOrCreate(
                ['event_ride_id' => $locked->id, 'discord_user_id' => $member->id],
                ['pickup_note' => $pickupNote],
            );
        });

        broadcast(new EventChanged($ride->event_id, EventChanged::TRANSPORT));
    }
}
