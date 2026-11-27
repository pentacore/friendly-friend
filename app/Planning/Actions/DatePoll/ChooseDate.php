<?php

declare(strict_types=1);

namespace App\Planning\Actions\DatePoll;

use App\Enums\DateVote;
use App\Enums\RsvpStatus;
use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventDateOption;
use App\Models\EventDateVote;
use App\Models\EventRsvp;
use App\Planning\Jobs\NotifyDateChosen;
use App\Planning\Jobs\SyncEventToDiscord;
use Illuminate\Support\Facades\DB;

/**
 * Ends the date vote: the event gets the chosen date, and everyone who
 * voted yes on it is going (if need be: maybe), unless they already
 * answered. Discord then gets the RSVP buttons and the native event, and
 * everyone who voted gets a DM with the date.
 */
final readonly class ChooseDate
{
    public function handle(Event $event, EventDateOption $option, ?DiscordUser $chosenBy = null): void
    {
        DB::transaction(function () use ($event, $option): void {
            $event->update([
                'starts_at' => $option->starts_at,
                'ends_at' => $option->ends_at,
                'date_polling' => false,
            ]);

            $answered = $event->rsvps()->pluck('discord_user_id')->all();

            $option->votes()
                ->whereIn('answer', [DateVote::Yes, DateVote::IfNeedBe])
                ->whereNotIn('discord_user_id', $answered)
                ->get()
                ->each(fn (EventDateVote $vote): EventRsvp => EventRsvp::query()->create([
                    'event_id' => $event->id,
                    'discord_user_id' => $vote->discord_user_id,
                    'status' => $vote->answer === DateVote::Yes ? RsvpStatus::Going : RsvpStatus::Maybe,
                ]));
        });

        broadcast(new EventChanged($event->id, EventChanged::OVERVIEW));
        dispatch(new SyncEventToDiscord($event->id));
        dispatch(new NotifyDateChosen($event->id, $chosenBy?->id));
    }
}
