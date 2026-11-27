<?php

declare(strict_types=1);

namespace App\Planning\Actions\Food;

use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventShoppingItem;

/**
 * Puts something on an event's shared shopping list (anyone taking part).
 */
final readonly class AddShoppingItem
{
    public function handle(Event $event, DiscordUser $member, string $label, ?string $quantity): EventShoppingItem
    {
        $item = $event->shoppingItems()->create([
            'label' => $label,
            'quantity' => $quantity,
            'added_by_discord_user_id' => $member->id,
        ]);

        broadcast(new EventChanged($event->id, EventChanged::FOOD));

        return $item;
    }
}
