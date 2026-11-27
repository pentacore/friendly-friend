<?php

declare(strict_types=1);

namespace App\Planning\Actions\Costs;

use App\Events\EventChanged;
use App\Models\EventPayment;

/**
 * Undoes a recorded payment.
 */
final readonly class RemovePayment
{
    public function handle(EventPayment $payment): void
    {
        $payment->delete();

        broadcast(new EventChanged($payment->event_id, EventChanged::COSTS));
    }
}
