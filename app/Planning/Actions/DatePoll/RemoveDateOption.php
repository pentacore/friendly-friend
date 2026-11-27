<?php

declare(strict_types=1);

namespace App\Planning\Actions\DatePoll;

use App\Events\EventChanged;
use App\Models\Event;
use App\Models\EventDateOption;
use App\Planning\Exceptions\LastDateOptionException;

/**
 * Withdraws a proposed date and its votes (organisers). The last one
 * stays: choose it instead.
 *
 * @throws LastDateOptionException
 */
final readonly class RemoveDateOption
{
    public function handle(Event $event, EventDateOption $option): void
    {
        if ($event->dateOptions()->count() <= 1) {
            throw new LastDateOptionException;
        }

        $option->delete();
        $event->update(['starts_at' => $event->dateOptions()->min('starts_at')]);

        broadcast(new EventChanged($event->id, EventChanged::OVERVIEW));
    }
}
