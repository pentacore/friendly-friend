<?php

declare(strict_types=1);

namespace App\Planning\Data;

use App\Enums\MealSlot;
use App\Enums\QuestionKind;
use App\Enums\RideDirection;
use Carbon\CarbonImmutable;

/**
 * What can be planned right away when creating an event, besides the
 * basics: questions, bring list, shopping, meals, the creator's own ride
 * and costs they paid (split among everyone going). All optional.
 */
final readonly class EventPlan
{
    /**
     * @param  list<array{prompt: string, kind: QuestionKind, options: list<string>}>  $questions
     * @param  list<array{label: string, quantity: int}>  $bring
     * @param  list<array{label: string, quantity: string|null}>  $shopping
     * @param  list<array{date: CarbonImmutable, slot: MealSlot, title: string}>  $meals
     * @param  array{direction: RideDirection, seats: int, departs_from: string|null, departs_at: CarbonImmutable|null}|null  $ride
     * @param  list<array{description: string, amount: int}>  $costs  minor units, paid by the creator
     */
    public function __construct(
        public array $questions = [],
        public array $bring = [],
        public array $shopping = [],
        public array $meals = [],
        public ?array $ride = null,
        public array $costs = [],
    ) {}
}
