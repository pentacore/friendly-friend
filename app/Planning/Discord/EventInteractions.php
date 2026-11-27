<?php

declare(strict_types=1);

namespace App\Planning\Discord;

use App\Access\Actions\RecordGuildMember;
use App\Access\GuildAccess;
use App\Discord\Data\GuildMemberData;
use App\Discord\Data\Payload;
use App\Discord\DiscordClient;
use App\Discord\Interactions\EventCustomId;
use App\Enums\DateVote;
use App\Enums\RsvpStatus;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventDateVote;
use App\Models\EventRsvp;
use App\Planning\Actions\DatePoll\CastDateVote;
use App\Planning\Actions\RecordRsvp;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Answers event components (already acknowledged): RSVP buttons (keeping
 * guests and notes set on the website), "Vote on dates" (the private date
 * picker) and the picker's select menus. Replies only to the person.
 */
final readonly class EventInteractions
{
    public function __construct(
        private DiscordClient $discord,
        private GuildAccess $access,
        private RecordGuildMember $recordMember,
        private RecordRsvp $recordRsvp,
        private CastDateVote $castVote,
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

        $this->discord->editInteractionResponse($token, is_string($reply) ? ['content' => $reply, 'components' => []] : $reply);
    }

    /**
     * Plain text, or a whole message (the date picker).
     *
     * @param  array<mixed>  $payload
     * @return string|array<string, mixed>
     */
    private function reply(array $payload): string|array
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

        if ($button->action === EventCustomId::RSVP && $button->status instanceof RsvpStatus) {
            return $this->rsvp($event, $actor, $button->status);
        }

        if (! $event->date_polling) {
            return 'The date of **'.$event->title.'** is already decided: <t:'.$event->starts_at->getTimestamp().':F>. Answer with the buttons on the announcement.';
        }

        $event->load('dateOptions.votes');

        if (! $button->isDateSelect()) {
            return DatePicker::message($event, $actor);
        }

        $this->vote($event, $actor, $button->action === EventCustomId::YES ? DateVote::Yes : DateVote::IfNeedBe, Payload::array($payload, 'data', 'values'));

        return DatePicker::message($event->load('dateOptions.votes'), $actor, 'Saved.');
    }

    private function rsvp(Event $event, DiscordUser $actor, RsvpStatus $status): string
    {
        if ($event->date_polling) {
            return 'The date of **'.$event->title.'** isn’t decided yet. Press **Vote on dates** instead.';
        }

        if ($event->rsvpClosed() && $status !== RsvpStatus::Declined) {
            return 'Answers for **'.$event->title.'** closed on <t:'.$event->rsvp_by?->getTimestamp().":f>. You can still say you can't, or ask an organiser.";
        }

        $current = EventRsvp::query()->where('event_id', $event->id)->where('discord_user_id', $actor->id)->first();
        $this->recordRsvp->handle($event, $actor, $status, $current->guests ?? 0, $current?->note, $current->needs_ride ?? false);

        $url = route('events.show', $event);
        $questions = $status !== RsvpStatus::Declined && $event->questions()->exists()
            ? ' The organisers have a few questions for you there.'
            : '';

        return match ($status) {
            RsvpStatus::Going => "You're going to **{$event->title}**. Food, rides and costs: {$url}{$questions}",
            RsvpStatus::Maybe => "Noted as maybe for **{$event->title}**. Details: {$url}{$questions}",
            RsvpStatus::Declined => "Noted: you can't make **{$event->title}**.",
        };
    }

    /**
     * One select menu was submitted: the picked dates get its answer;
     * dates that had this answer (or none) and weren't picked become no.
     * Votes from the other menu are left alone.
     *
     * @param  array<mixed>  $picked  option IDs as strings
     */
    private function vote(Event $event, DiscordUser $actor, DateVote $answer, array $picked): void
    {
        $picked = array_map(strval(...), array_filter($picked, is_string(...)));

        foreach ($event->dateOptions as $option) {
            $current = $option->votes->first(fn (EventDateVote $vote): bool => $vote->discord_user_id === $actor->id)?->answer;

            if (in_array((string) $option->id, $picked, true)) {
                $this->castVote->handle($option, $actor, $answer);
            } elseif ($current === $answer || $current === null) {
                $this->castVote->handle($option, $actor, DateVote::No);
            }
        }
    }
}
