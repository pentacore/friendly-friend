<?php

declare(strict_types=1);

namespace App\Planning\Actions\Food;

use App\Enums\MealSlot;
use App\Events\EventChanged;
use App\Models\Event;
use App\Models\EventMeal;
use Carbon\CarbonImmutable;

/**
 * Adds a meal to the plan, or changes one (organisers).
 */
final readonly class SaveMeal
{
    public function handle(Event $event, ?EventMeal $meal, CarbonImmutable $date, MealSlot $slot, string $title, ?string $notes): EventMeal
    {
        $attributes = ['date' => $date->toDateString(), 'slot' => $slot, 'title' => $title, 'notes' => $notes];

        if ($meal instanceof EventMeal) {
            $meal->update($attributes);
        } else {
            $meal = $event->meals()->create($attributes);
        }

        broadcast(new EventChanged($event->id, EventChanged::FOOD));

        return $meal;
    }
}
