<?php

declare(strict_types=1);

namespace App\Planning\Actions\Costs;

use App\Events\EventChanged;
use App\Models\EventExpense;

/**
 * Deletes an expense and its shares.
 */
final readonly class RemoveExpense
{
    public function handle(EventExpense $expense): void
    {
        $expense->delete();

        broadcast(new EventChanged($expense->event_id, EventChanged::COSTS));
    }
}
