<?php

declare(strict_types=1);

namespace App\Planning\Jobs;

use App\Discord\DiscordClient;
use App\Enums\EventStatus;
use App\Enums\RsvpStatus;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventRsvp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs every 5 minutes: DMs everyone going to an event 24 hours and 2
 * hours before it starts. People whose DMs are closed are mentioned in the
 * events channel instead (the one deliberate ping the bot makes). Each
 * reminder is sent once (`reminded_day_at`, `reminded_hours_at`); an event
 * created less than 2 hours ahead only gets the second one.
 */
final class SendEventReminders implements ShouldQueue
{
    use Queueable;

    public function handle(DiscordClient $discord): void
    {
        if (! $discord->configured()) {
            return;
        }

        foreach ($this->due('reminded_hours_at', 2) as $event) {
            $event->forceFill(['reminded_hours_at' => now(), 'reminded_day_at' => $event->reminded_day_at ?? now()])->saveQuietly();
            $this->remind($discord, $event);
        }

        foreach ($this->due('reminded_day_at', 24) as $event) {
            $event->forceFill(['reminded_day_at' => now()])->saveQuietly();
            $this->remind($discord, $event);
        }
    }

    /**
     * Planned events starting within the next $hours that haven't had this reminder.
     *
     * @return iterable<int, Event>
     */
    private function due(string $column, int $hours): iterable
    {
        return Event::query()
            ->with(['guild', 'rsvps.discordUser'])
            ->where('status', EventStatus::Planned)
            ->whereNull($column)
            ->whereBetween('starts_at', [now(), now()->addHours($hours)])
            ->whereHas('guild', fn (Builder $guild) => $guild->where('enabled', true))
            ->get();
    }

    private function remind(DiscordClient $discord, Event $event): void
    {
        $url = route('events.show', $event);
        $start = $event->starts_at->getTimestamp();
        $text = "**{$event->title}** starts <t:{$start}:R> (<t:{$start}:t>)".($event->location !== null ? " at {$event->location}" : '').". {$url}";
        $unreachable = [];

        $going = $event->rsvps
            ->filter(fn (EventRsvp $rsvp): bool => $rsvp->status === RsvpStatus::Going)
            ->map(fn (EventRsvp $rsvp): DiscordUser => $rsvp->discordUser);

        foreach ($going as $member) {
            try {
                if (! $discord->sendDirectMessage($member->discord_id, ['content' => "Reminder: {$text}"])) {
                    $unreachable[] = $member->discord_id;
                }
            } catch (Throwable $e) {
                Log::warning("Couldn't DM an event reminder.", ['event' => $event->id, 'exception' => $e]);
                $unreachable[] = $member->discord_id;
            }
        }

        $channelId = $event->announce_channel_id ?? $event->guild->events_channel_id;

        if ($unreachable === [] || $channelId === null) {
            return;
        }

        $mentions = implode(' ', array_map(fn (string $id): string => "<@{$id}>", $unreachable));
        $discord->sendMessage($channelId, [
            'content' => "{$mentions} Reminder: {$text}",
            'allowed_mentions' => ['users' => $unreachable],
        ]);
    }
}
