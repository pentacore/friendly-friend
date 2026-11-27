<?php

declare(strict_types=1);

namespace App\Planning\Actions;

use App\Enums\EventStatus;
use App\Events\EventChanged;
use App\Models\Event;
use App\Planning\Jobs\SyncEventToDiscord;

/**
 * Calls an event off (or back on). Cancelled events stay visible, so their
 * costs can still be settled.
 */
final readonly class CancelEvent
{
    public function handle(Event $event, bool $cancelled = true): void
    {
        $event->update(['status' => $cancelled ? EventStatus::Cancelled : EventStatus::Planned]);

        broadcast(new EventChanged($event->id, EventChanged::OVERVIEW));
        dispatch(new SyncEventToDiscord($event->id));
    }
}
