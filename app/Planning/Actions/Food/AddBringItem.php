<?php

declare(strict_types=1);

namespace App\Planning\Actions\Food;

use App\Events\EventChanged;
use App\Models\Event;
use App\Models\EventBringItem;

/**
 * Adds something to an event's bring list (organisers).
 */
final readonly class AddBringItem
{
    public function handle(Event $event, string $label, int $quantityNeeded): EventBringItem
    {
        $item = $event->bringItems()->create(['label' => $label, 'quantity_needed' => max(1, $quantityNeeded)]);

        broadcast(new EventChanged($event->id, EventChanged::FOOD));

        return $item;
    }
}
