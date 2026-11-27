<?php

declare(strict_types=1);

namespace App\Planning\Actions\Costs;

use App\Enums\SplitMode;
use App\Events\EventChanged;
use App\Models\EventExpense;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Changes an expense: what, how much, who paid, and how it's split.
 */
final readonly class UpdateExpense
{
    /**
     * @param  array<int, int>  $shares  discord_users.id => minor units; must add up to $amount (ignored for SplitMode::Attendees)
     */
    public function handle(EventExpense $expense, int $paidById, string $description, int $amount, SplitMode $mode, array $shares): EventExpense
    {
        $fixedShares = $mode !== SplitMode::Attendees;

        if ($amount <= 0 || ($fixedShares && ($shares === [] || array_sum($shares) !== $amount))) {
            throw new InvalidArgumentException('The shares must add up to the amount.');
        }

        DB::transaction(function () use ($expense, $paidById, $description, $amount, $mode, $shares, $fixedShares): void {
            $expense->update([
                'paid_by_discord_user_id' => $paidById,
                'description' => $description,
                'amount' => $amount,
                'split_mode' => $mode,
            ]);

            $expense->shares()->delete();

            foreach ($fixedShares ? $shares : [] as $personId => $share) {
                $expense->shares()->create(['discord_user_id' => $personId, 'amount' => $share]);
            }
        });

        broadcast(new EventChanged($expense->event_id, EventChanged::COSTS));

        return $expense;
    }
}
