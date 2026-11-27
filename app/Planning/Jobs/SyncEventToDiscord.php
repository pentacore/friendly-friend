<?php

declare(strict_types=1);

namespace App\Planning\Jobs;

use App\Discord\DiscordClient;
use App\Models\Event;
use App\Planning\Discord\EventAnnouncement;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;

/**
 * Posts or edits an event's announcement in the server's events channel
 * and, when the details changed, mirrors it as a native scheduled event.
 * Unique while queued, so a burst of RSVPs edits the message once.
 * Discord refusing (missing permission, deleted channel) is logged, not
 * retried.
 */
#[Tries(3)]
final class SyncEventToDiscord implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $eventId, public readonly bool $details = true) {}

    public function uniqueId(): string
    {
        return $this->eventId.':'.($this->details ? 'details' : 'counts');
    }

    public function handle(DiscordClient $discord): void
    {
        $event = Event::query()->with(['guild', 'rsvps', 'dateOptions.votes'])->find($this->eventId);

        if ($event === null || ! $discord->configured() || ! $event->guild->enabled || ! $event->guild->bot_present) {
            return;
        }

        $this->announce($discord, $event);

        // Details change the whole native event; answers change the counts in its description.
        if ($this->details || $event->discord_scheduled_event_id !== null) {
            $this->mirror($discord, $event);
        }
    }

    private function announce(DiscordClient $discord, Event $event): void
    {
        $channelId = $event->announce_channel_id ?? $event->guild->events_channel_id;

        if ($channelId === null || ($event->discord_message_id === null && $event->isCancelled())) {
            return;
        }

        try {
            if ($event->discord_message_id === null) {
                $messageId = $discord->sendMessage($channelId, EventAnnouncement::message($event));
                $event->forceFill(['announce_channel_id' => $channelId, 'discord_message_id' => $messageId])->saveQuietly();
            } else {
                $discord->editMessage($channelId, $event->discord_message_id, EventAnnouncement::message($event));
            }
        } catch (RequestException $e) {
            $this->unlessClientError($e, "Couldn't post or edit the announcement of event {$event->id} (does the bot see the events channel?).");
        }
    }

    private function mirror(DiscordClient $discord, Event $event): void
    {
        $alreadyMirrored = $event->discord_scheduled_event_id !== null;

        if (! $alreadyMirrored && ($event->isCancelled() || $event->date_polling || $event->starts_at->isPast())) {
            return;
        }

        try {
            $id = $discord->saveScheduledEvent($event->guild->discord_id, $event->discord_scheduled_event_id, EventAnnouncement::scheduledEvent($event));
            $event->forceFill(['discord_scheduled_event_id' => $id])->saveQuietly();
        } catch (RequestException $e) {
            $this->unlessClientError($e, "Couldn't mirror event {$event->id} as a Discord event (the bot needs Create Events / Manage Events).");
        }
    }

    /**
     * 4xx means Discord won't accept it however often we try: log and move on.
     */
    private function unlessClientError(RequestException $e, string $message): void
    {
        if (! $e->response->clientError()) {
            throw $e;
        }

        Log::warning($message, ['status' => $e->response->status(), 'body' => $e->response->body()]);
    }
}
