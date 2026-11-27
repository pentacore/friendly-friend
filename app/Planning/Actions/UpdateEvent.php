<?php

declare(strict_types=1);

namespace App\Planning\Actions;

use App\Events\EventChanged;
use App\Models\Event;
use App\Planning\Data\EventDetails;
use App\Planning\EventAttributes;
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
            $event->update($this->attributes->columns($details));
            $this->attributes->syncHosts($event, $details->hostIds);
        });

        broadcast(new EventChanged($event->id, EventChanged::OVERVIEW));

        return $event;
    }
}
