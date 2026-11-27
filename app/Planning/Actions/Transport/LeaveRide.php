<?php

declare(strict_types=1);

namespace App\Planning\Actions\Transport;

use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\EventRide;

/**
 * Gives up a seat (yourself, or the driver/an organiser removing someone).
 */
final readonly class LeaveRide
{
    public function handle(EventRide $ride, DiscordUser $passenger): void
    {
        $ride->passengers()->where('discord_user_id', $passenger->id)->delete();

        broadcast(new EventChanged($ride->event_id, EventChanged::TRANSPORT));
    }
}
