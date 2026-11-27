<?php

declare(strict_types=1);

namespace App\Planning\Actions\Transport;

use App\Enums\RideDirection;
use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventRide;
use Carbon\CarbonImmutable;

/**
 * "I'm driving and have N free seats."
 */
final readonly class OfferRide
{
    public function handle(Event $event, DiscordUser $driver, RideDirection $direction, int $seats, ?string $departsFrom, ?CarbonImmutable $departsAt, ?string $note): EventRide
    {
        $ride = $event->rides()->create([
            'driver_discord_user_id' => $driver->id,
            'direction' => $direction,
            'seats' => max(1, $seats),
            'departs_from' => $departsFrom,
            'departs_at' => $departsAt,
            'note' => $note,
        ]);

        broadcast(new EventChanged($event->id, EventChanged::TRANSPORT));

        return $ride;
    }
}
