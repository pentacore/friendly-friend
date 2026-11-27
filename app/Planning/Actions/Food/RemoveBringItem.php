<?php

declare(strict_types=1);

namespace App\Planning\Actions\Food;

use App\Events\EventChanged;
use App\Models\EventBringItem;

/**
 * Takes something off the bring list, with its claims (organisers).
 */
final readonly class RemoveBringItem
{
    public function handle(EventBringItem $item): void
    {
        $item->delete();

        broadcast(new EventChanged($item->event_id, EventChanged::FOOD));
    }
}
