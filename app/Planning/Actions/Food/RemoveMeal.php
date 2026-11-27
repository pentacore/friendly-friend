<?php

declare(strict_types=1);

namespace App\Planning\Actions\Food;

use App\Events\EventChanged;
use App\Models\EventMeal;

/**
 * Takes a meal off the plan (organisers).
 */
final readonly class RemoveMeal
{
    public function handle(EventMeal $meal): void
    {
        $meal->delete();

        broadcast(new EventChanged($meal->event_id, EventChanged::FOOD));
    }
}
