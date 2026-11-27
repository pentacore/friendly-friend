<?php

declare(strict_types=1);

namespace App\Planning\Actions\Food;

use App\Events\EventChanged;
use App\Models\EventShoppingItem;

/**
 * Takes something off the shopping list (whoever added it, or organisers).
 */
final readonly class RemoveShoppingItem
{
    public function handle(EventShoppingItem $item): void
    {
        $item->delete();

        broadcast(new EventChanged($item->event_id, EventChanged::FOOD));
    }
}
