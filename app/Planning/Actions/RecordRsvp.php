<?php

declare(strict_types=1);

namespace App\Planning\Actions;

use App\Enums\RsvpStatus;
use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Planning\Jobs\SyncEventToDiscord;

/**
 * Sets someone's answer to an event (website or Discord button). Guests
 * and "needs a ride" only count while going or maybe.
 */
final readonly class RecordRsvp
{
    public function handle(Event $event, DiscordUser $member, RsvpStatus $status, int $guests = 0, ?string $note = null, bool $needsRide = false): EventRsvp
    {
        $coming = $status !== RsvpStatus::Declined;

        $rsvp = EventRsvp::query()->updateOrCreate(
            ['event_id' => $event->id, 'discord_user_id' => $member->id],
            [
                'status' => $status,
                'guests' => $coming ? max(0, $guests) : 0,
                'note' => $note,
                'needs_ride' => $coming && $needsRide,
                // A real answer replaces one made by Discord's "Interested".
                'from_discord_interest' => false,
            ],
        );

        broadcast(new EventChanged($event->id, EventChanged::OVERVIEW));
        dispatch(new SyncEventToDiscord($event->id, details: false));

        return $rsvp;
    }
}
