<?php

declare(strict_types=1);

namespace App\Planning\Jobs;

use App\Access\GuildAccess;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Planning\Actions\ApplyDiscordInterest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * GUILD_SCHEDULED_EVENT_USER_ADD / _REMOVE: "Interested" on a mirrored
 * event. Ignored for unknown events or people, people without access,
 * cancelled events, undecided dates and closed answers.
 */
#[Tries(3)]
final class SyncDiscordInterest implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $guildDiscordId,
        public readonly string $scheduledEventId,
        public readonly string $memberDiscordId,
        public readonly bool $interested,
    ) {}

    public function handle(GuildAccess $access, ApplyDiscordInterest $apply): void
    {
        $event = Event::query()->with('guild')->where('discord_scheduled_event_id', $this->scheduledEventId)->first();
        $member = DiscordUser::query()->where('discord_id', $this->memberDiscordId)->first();

        if ($event === null || $member === null || $event->guild->discord_id !== $this->guildDiscordId
            || $event->isCancelled() || $event->date_polling || ! $access->allowsInGuild($member, $event->guild)) {
            return;
        }

        // After the answer-by time, Interested can't make anyone going (taking it back still can).
        if ($this->interested && $event->rsvpClosed()) {
            return;
        }

        $apply->handle($event, $member, $this->interested);
    }
}
