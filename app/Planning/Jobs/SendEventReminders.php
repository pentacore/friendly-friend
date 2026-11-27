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
 * created less than 2 hours ahead only gets the second one. Also nudges
 * people on Maybe a day before the "answer by" time, and refreshes the
 * announcement once answers close.
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

        $this->nudgeMaybes($discord);
        $this->closeAnswers();
    }

    /**
     * A day before the "answer by" time, ask everyone still on Maybe to decide.
     */
    private function nudgeMaybes(DiscordClient $discord): void
    {
        $events = Event::query()
            ->with(['guild', 'rsvps.discordUser'])
            ->where('status', EventStatus::Planned)
            ->where('date_polling', false)
            ->whereNull('reminded_rsvp_at')
            ->whereBetween('rsvp_by', [now(), now()->addDay()])
            ->whereHas('guild', fn (Builder $guild) => $guild->where('enabled', true))
            ->get();

        foreach ($events as $event) {
            $event->forceFill(['reminded_rsvp_at' => now()])->saveQuietly();
            $by = $event->rsvp_by?->getTimestamp();
            $url = route('events.show', $event);

            $event->rsvps
                ->filter(fn (EventRsvp $rsvp): bool => $rsvp->status === RsvpStatus::Maybe)
                ->each(function (EventRsvp $rsvp) use ($discord, $event, $by, $url): void {
                    try {
                        $discord->sendDirectMessage($rsvp->discordUser->discord_id, [
                            'content' => "You're down as maybe for **{$event->title}**. Answers close <t:{$by}:R>: are you coming? {$url}",
                        ]);
                    } catch (Throwable $e) {
                        Log::warning("Couldn't DM an answer-by nudge.", ['event' => $event->id, 'exception' => $e]);
                    }
                });
        }
    }

    /**
     * Once the "answer by" time passes, refresh the announcement so Going and Maybe grey out.
     */
    private function closeAnswers(): void
    {
        Event::query()
            ->where('status', EventStatus::Planned)
            ->whereNull('rsvp_closed_synced_at')
            ->where('rsvp_by', '<=', now())
            ->whereNotNull('discord_message_id')
            ->get()
            ->each(function (Event $event): void {
                $event->forceFill(['rsvp_closed_synced_at' => now()])->saveQuietly();
                dispatch(new SyncEventToDiscord($event->id, details: false));
            });
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
            ->where('date_polling', false)
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
