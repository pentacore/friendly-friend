<?php

declare(strict_types=1);

namespace App\Planning\Actions;

use App\Events\EventChanged;
use App\Models\Event;
use App\Planning\Data\EventDetails;
use App\Planning\EventAttributes;
use App\Planning\Jobs\SyncEventToDiscord;
use Illuminate\Support\Facades\DB;

/**
 * Saves the event form for an existing event.
 */
final readonly class UpdateEvent
{
    public function __construct(private EventAttributes $attributes) {}

    public function handle(Event $event, EventDetails $details): Event
    {
        DB::transaction(function () use ($event, $details): void {
            $columns = $this->attributes->columns($details);

            // While the date is being voted on, the date comes from the vote.
            $event->update($event->date_polling ? array_diff_key($columns, array_flip(['starts_at', 'ends_at'])) : $columns);
            $this->attributes->syncHosts($event, $details->hostIds);
        });

        broadcast(new EventChanged($event->id, EventChanged::OVERVIEW));
        dispatch(new SyncEventToDiscord($event->id));

        return $event;
    }
}
