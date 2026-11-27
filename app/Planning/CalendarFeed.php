<?php

declare(strict_types=1);

namespace App\Planning;

use App\Access\AccessSnapshot;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The events in someone's personal calendar feed: in servers they can see
 * now, with a decided date, starting at most 60 days ago, that they're going
 * to or might go to, host or created. Cancelled ones stay, marked cancelled,
 * so calendar apps take them out.
 */
final readonly class CalendarFeed
{
    private const int DAYS_BACK = 60;

    /**
     * @return Collection<int, Event>
     */
    public static function eventsFor(User $user): Collection
    {
        $access = AccessSnapshot::for($user);
        $me = $user->discord_user_id;

        return Event::query()
            ->whereIn('guild_id', $access->guildIds)
            ->where('date_polling', false)
            ->where('starts_at', '>=', now()->subDays(self::DAYS_BACK))
            ->where(fn (Builder $mine) => $mine
                ->where('created_by_discord_user_id', $me)
                ->orWhereHas('hosts', fn (Builder $hosts) => $hosts->whereKey($me))
                ->orWhereHas('rsvps', fn (Builder $rsvps) => $rsvps
                    ->where('discord_user_id', $me)
                    ->whereIn('status', [RsvpStatus::Going, RsvpStatus::Maybe])))
            ->orderBy('starts_at')
            ->get();
    }
}
