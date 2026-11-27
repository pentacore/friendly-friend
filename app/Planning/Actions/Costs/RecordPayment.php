<?php

declare(strict_types=1);

namespace App\Planning\Actions\Costs;

use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventPayment;

/**
 * "Paid": money went from one person to another to settle up.
 */
final readonly class RecordPayment
{
    public function handle(Event $event, int $fromId, int $toId, int $amount, DiscordUser $recordedBy): EventPayment
    {
        $payment = $event->payments()->create([
            'from_discord_user_id' => $fromId,
            'to_discord_user_id' => $toId,
            'recorded_by_discord_user_id' => $recordedBy->id,
            'amount' => $amount,
            'settled_at' => now(),
        ]);

        broadcast(new EventChanged($event->id, EventChanged::COSTS));

        return $payment;
    }
}
