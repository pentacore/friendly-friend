<?php

declare(strict_types=1);

namespace App\Planning\Actions\Food;

use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\EventMeal;

/**
 * Volunteers someone to cook a meal, or takes them off it.
 */
final readonly class ToggleCook
{
    public function handle(EventMeal $meal, DiscordUser $member, bool $cooking): void
    {
        if ($cooking) {
            $meal->cooks()->syncWithoutDetaching([$member->id]);
        } else {
            $meal->cooks()->detach($member->id);
        }

        broadcast(new EventChanged($meal->event_id, EventChanged::FOOD));
    }
}
