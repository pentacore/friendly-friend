<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * When in the day a meal is, in the order they're shown.
 */
enum MealSlot: string
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';
    case Snack = 'snack';
}
