<?php

declare(strict_types=1);

namespace App\Planning\Actions\Costs;

use App\Events\EventChanged;
use App\Models\EventExpense;
use App\Models\EventShoppingItem;
use Illuminate\Support\Facades\DB;

/**
 * Sets which items of the event's shopping list an expense paid for.
 * Replaces the previous links; an item can belong to one expense, so
 * picking it here moves it from any other.
 */
final readonly class CoverShoppingItems
{
    /**
     * @param  list<int>  $itemIds  event_shopping_items.id of the same event
     */
    public function handle(EventExpense $expense, array $itemIds): void
    {
        DB::transaction(function () use ($expense, $itemIds): void {
            EventShoppingItem::query()
                ->where('expense_id', $expense->id)
                ->whereNotIn('id', $itemIds)
                ->update(['expense_id' => null]);

            EventShoppingItem::query()
                ->where('event_id', $expense->event_id)
                ->whereIn('id', $itemIds)
                ->update(['expense_id' => $expense->id]);
        });

        broadcast(new EventChanged($expense->event_id, EventChanged::FOOD));
    }
}
