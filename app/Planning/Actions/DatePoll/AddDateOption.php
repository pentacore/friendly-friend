<?php

declare(strict_types=1);

namespace App\Planning\Actions\DatePoll;

use App\Events\EventChanged;
use App\Models\Event;
use App\Models\EventDateOption;
use Carbon\CarbonImmutable;

/**
 * Proposes another date while the date is being voted on (organisers).
 */
final readonly class AddDateOption
{
    public function handle(Event $event, CarbonImmutable $startsAt, ?CarbonImmutable $endsAt): EventDateOption
    {
        $option = $event->dateOptions()->create(['starts_at' => $startsAt, 'ends_at' => $endsAt]);
        $event->update(['starts_at' => $event->dateOptions()->min('starts_at')]);

        broadcast(new EventChanged($event->id, EventChanged::OVERVIEW));

        return $option;
    }
}
