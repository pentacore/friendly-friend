<?php

declare(strict_types=1);

namespace App\Planning\Jobs;

use App\Discord\DiscordClient;
use App\Enums\RsvpStatus;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventDateOption;
use App\Models\EventDateVote;
use App\Models\EventRsvp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * After a date vote ends: a DM to everyone who voted (except whoever chose)
 * saying when it is and where they stand. Closed DMs are skipped.
 */
#[Tries(1)]
final class NotifyDateChosen implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $eventId, public readonly ?int $chosenByDiscordUserId = null) {}

    public function handle(DiscordClient $discord): void
    {
        $event = Event::query()->with(['dateOptions.votes.discordUser', 'rsvps'])->find($this->eventId);

        if ($event === null || $event->date_polling || ! $discord->configured()) {
            return;
        }

        $voters = $event->dateOptions
            ->flatMap(fn (EventDateOption $option): Collection => $option->votes)
            ->map(fn (EventDateVote $vote): DiscordUser => $vote->discordUser)
            ->unique('id')
            ->reject(fn (DiscordUser $voter): bool => $voter->id === $this->chosenByDiscordUserId);

        $start = $event->starts_at->getTimestamp();
        $url = route('events.show', $event);

        foreach ($voters as $voter) {
            $status = $event->rsvps->first(fn (EventRsvp $rsvp): bool => $rsvp->discord_user_id === $voter->id)?->status;
            $where = match ($status) {
                RsvpStatus::Going => "You're down as going.",
                RsvpStatus::Maybe => "You're down as maybe. Let us know for sure:",
                RsvpStatus::Declined => "You're down as can't make it.",
                null => 'You said no to this date. Can you make it after all?',
            };

            try {
                $discord->sendDirectMessage($voter->discord_id, [
                    'content' => "The date for **{$event->title}** is set: <t:{$start}:F> (<t:{$start}:R>). {$where} {$url}",
                ]);
            } catch (Throwable $e) {
                Log::warning("Couldn't DM a voter about the chosen date.", ['event' => $event->id, 'exception' => $e->getMessage()]);
            }
        }
    }
}
