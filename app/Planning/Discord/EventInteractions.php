<?php

declare(strict_types=1);

namespace App\Planning\Discord;

use App\Access\Actions\RecordGuildMember;
use App\Access\GuildAccess;
use App\Discord\Data\GuildMemberData;
use App\Discord\Data\Payload;
use App\Discord\DiscordClient;
use App\Discord\Interactions\EventCustomId;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Planning\Actions\RecordRsvp;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Answers an RSVP button on an event announcement (already acknowledged
 * privately): records the answer, keeping guests and notes set on the
 * website, and replies only to the person who pressed it.
 */
final readonly class EventInteractions
{
    public function __construct(
        private DiscordClient $discord,
        private GuildAccess $access,
        private RecordGuildMember $recordMember,
        private RecordRsvp $recordRsvp,
    ) {}

    /**
     * @param  array<mixed>  $payload  INTERACTION_CREATE
     */
    public function handle(array $payload): void
    {
        $token = Payload::string($payload, 'token');

        if ($token === null) {
            return;
        }

        try {
            $reply = $this->reply($payload);
        } catch (Throwable $e) {
            Log::error('Event interaction failed.', ['exception' => $e]);
            $reply = 'Something went wrong. Please try again, or answer on the website.';
        }

        $this->discord->editInteractionResponse($token, ['content' => $reply, 'components' => []]);
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function reply(array $payload): string
    {
        $button = EventCustomId::parse(Payload::string($payload, 'data', 'custom_id'));
        $event = $button instanceof EventCustomId ? Event::query()->with('guild')->find($button->eventId) : null;

        if (! $button instanceof EventCustomId || $event === null || $event->guild->discord_id !== Payload::string($payload, 'guild_id') || ! $event->guild->enabled) {
            return 'That event is gone.';
        }

        if ($event->isCancelled()) {
            return "**{$event->title}** was cancelled.";
        }

        $member = GuildMemberData::fromPayload(Payload::array($payload, 'member'));

        if (! $member->isValid()) {
            return "I couldn't tell who you are.";
        }

        $actor = $this->recordMember->handle($event->guild, $member)->discordUser;

        if (! $this->access->allowsInGuild($actor, $event->guild)) {
            return "You don't have access to events on this server.";
        }

        $current = EventRsvp::query()->where('event_id', $event->id)->where('discord_user_id', $actor->id)->first();
        $this->recordRsvp->handle($event, $actor, $button->status, $current->guests ?? 0, $current?->note, $current->needs_ride ?? false);

        $url = route('events.show', $event);

        return match ($button->status) {
            RsvpStatus::Going => "You're going to **{$event->title}**. Food, rides and costs: {$url}",
            RsvpStatus::Maybe => "Noted as maybe for **{$event->title}**. Details: {$url}",
            RsvpStatus::Declined => "Noted: you can't make **{$event->title}**.",
        };
    }
}
