<?php

declare(strict_types=1);

namespace App\Planning\Actions\Food;

use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\EventShoppingItem;

/**
 * Marks a shopping item as bought (by whom, when), or not bought after all.
 */
final readonly class TickShoppingItem
{
    public function handle(EventShoppingItem $item, DiscordUser $member, bool $bought): void
    {
        $item->update($bought
            ? ['checked_by_discord_user_id' => $member->id, 'checked_at' => now()]
            : ['checked_by_discord_user_id' => null, 'checked_at' => null]);

        broadcast(new EventChanged($item->event_id, EventChanged::FOOD));
    }
}
