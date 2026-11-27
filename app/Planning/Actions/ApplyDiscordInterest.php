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
 * Someone pressed (or took back) "Interested" on the native Discord event.
 * Interested means going, unless they already answered some other way.
 * Taking it back only removes an RSVP that "Interested" made.
 */
final readonly class ApplyDiscordInterest
{
    public function __construct(private RecordRsvp $recordRsvp) {}

    public function handle(Event $event, DiscordUser $member, bool $interested): void
    {
        $rsvp = EventRsvp::query()->where('event_id', $event->id)->where('discord_user_id', $member->id)->first();

        if ($interested) {
            if ($rsvp === null) {
                $this->recordRsvp->handle($event, $member, RsvpStatus::Going);
                EventRsvp::query()->where('event_id', $event->id)->where('discord_user_id', $member->id)->update(['from_discord_interest' => true]);
            }

            return;
        }

        if ($rsvp?->from_discord_interest === true) {
            $rsvp->delete();

            broadcast(new EventChanged($event->id, EventChanged::OVERVIEW));
            dispatch(new SyncEventToDiscord($event->id, details: false));
        }
    }
}
