<?php

declare(strict_types=1);

namespace App\Planning\Actions\Costs;

use App\Enums\SplitMode;
use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventExpense;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records something someone paid, and who shares it.
 */
final readonly class AddExpense
{
    /**
     * @param  array<int, int>  $shares  discord_users.id => minor units; must add up to $amount
     */
    public function handle(Event $event, DiscordUser $addedBy, int $paidById, string $description, int $amount, SplitMode $mode, array $shares): EventExpense
    {
        if ($amount <= 0 || $shares === [] || array_sum($shares) !== $amount) {
            throw new InvalidArgumentException('The shares must add up to the amount.');
        }

        $expense = DB::transaction(function () use ($event, $addedBy, $paidById, $description, $amount, $mode, $shares): EventExpense {
            $expense = $event->expenses()->create([
                'paid_by_discord_user_id' => $paidById,
                'added_by_discord_user_id' => $addedBy->id,
                'description' => $description,
                'amount' => $amount,
                'split_mode' => $mode,
            ]);

            foreach ($shares as $personId => $share) {
                $expense->shares()->create(['discord_user_id' => $personId, 'amount' => $share]);
            }

            return $expense;
        });

        broadcast(new EventChanged($event->id, EventChanged::COSTS));

        return $expense;
    }
}
