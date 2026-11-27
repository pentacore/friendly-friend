<?php

declare(strict_types=1);

namespace App\Planning\Actions\Costs;

use App\Events\EventChanged;
use App\Models\EventExpense;

/**
 * Deletes an expense and its shares; shopping items it paid for are
 * unlinked (by the foreign key).
 */
final readonly class RemoveExpense
{
    public function handle(EventExpense $expense): void
    {
        $paidForShopping = $expense->shoppingItems()->exists();
        $expense->delete();

        broadcast(new EventChanged($expense->event_id, EventChanged::COSTS));

        if ($paidForShopping) {
            broadcast(new EventChanged($expense->event_id, EventChanged::FOOD));
        }
    }
}
