<?php

declare(strict_types=1);

namespace App\Planning\Actions\Food;

use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\EventBringClaim;
use App\Models\EventBringItem;

/**
 * "I'll bring it" (or how many of it). Quantity 0 takes the claim back.
 */
final readonly class ClaimBringItem
{
    public function handle(EventBringItem $item, DiscordUser $member, int $quantity, ?string $note = null): void
    {
        if ($quantity <= 0) {
            EventBringClaim::query()->where('event_bring_item_id', $item->id)->where('discord_user_id', $member->id)->delete();
        } else {
            EventBringClaim::query()->updateOrCreate(
                ['event_bring_item_id' => $item->id, 'discord_user_id' => $member->id],
                ['quantity' => $quantity, 'note' => $note],
            );
        }

        broadcast(new EventChanged($item->event_id, EventChanged::FOOD));
    }
}
