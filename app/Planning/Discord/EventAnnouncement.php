<?php

declare(strict_types=1);

namespace App\Planning\Discord;

use App\Discord\Interactions\EventCustomId;
use App\Enums\DateVote;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\EventDateOption;
use App\Models\EventDateVote;
use App\Models\EventRsvp;
use Illuminate\Support\Str;

/**
 * What the bot posts for an event: the announcement (embed + RSVP buttons)
 * and the native scheduled event it mirrors. Pure: built from the model.
 */
final class EventAnnouncement
{
    private const int ACTION_ROW = 1;

    private const int BUTTON = 2;

    private const int SUCCESS = 3;

    private const int PRIMARY = 1;

    private const int SECONDARY = 2;

    private const int DANGER = 4;

    private const int EXTERNAL = 3;

    private const int GUILD_ONLY = 2;

    private const int CANCELLED = 4;

    /** Discord needs an end time for external events. */
    private const int ASSUMED_HOURS = 6;

    /**
     * Load `rsvps` first.
     *
     * @return array<string, mixed>
     */
    public static function message(Event $event): array
    {
        $count = fn (RsvpStatus $status): int => $event->rsvps->filter(fn (EventRsvp $rsvp): bool => $rsvp->status === $status)->count();
        $guests = $event->rsvps->filter(fn (EventRsvp $rsvp): bool => $rsvp->status === RsvpStatus::Going)->sum(fn (EventRsvp $rsvp): int => $rsvp->guests);
        $going = $count(RsvpStatus::Going).($guests > 0 ? " + {$guests}" : '');
        $start = $event->starts_at->getTimestamp();

        $fields = [
            ['name' => 'When', 'value' => $event->date_polling ? self::candidates($event) : "<t:{$start}:F> (<t:{$start}:R>)", 'inline' => false],
        ];

        if ($event->rsvp_by !== null) {
            $by = $event->rsvp_by->getTimestamp();
            $fields[] = ['name' => 'Answer by', 'value' => $event->rsvpClosed() ? "<t:{$by}:f> (closed; you can still say you can't)" : "<t:{$by}:f> (<t:{$by}:R>)", 'inline' => false];
        }

        if ($event->location !== null) {
            $fields[] = ['name' => 'Where', 'value' => $event->location_url !== null ? "[{$event->location}]({$event->location_url})" : $event->location, 'inline' => false];
        }

        if (! $event->date_polling) {
            $fields[] = ['name' => 'Coming', 'value' => "{$going} going · {$count(RsvpStatus::Maybe)} maybe · {$count(RsvpStatus::Declined)} can't", 'inline' => false];
        }

        return [
            'content' => '',
            'embeds' => [[
                'title' => ($event->isCancelled() ? 'Cancelled: ' : '').$event->title,
                'url' => route('events.show', $event),
                'description' => Str::limit((string) $event->description, 500),
                'fields' => $fields,
                'footer' => ['text' => 'Food, rides and costs are on the website.'],
            ]],
            // Until the date is decided: one button that opens the private date picker.
            'components' => $event->date_polling ? [self::voteOnDatesRow($event)] : [[
                'type' => self::ACTION_ROW,
                'components' => [
                    self::button($event, RsvpStatus::Going, self::SUCCESS),
                    self::button($event, RsvpStatus::Maybe, self::SECONDARY),
                    self::button($event, RsvpStatus::Declined, self::DANGER),
                ],
            ]],
        ];
    }

    /**
     * "Not decided yet" plus the proposed dates with their tallies. Load `dateOptions.votes`.
     */
    private static function candidates(Event $event): string
    {
        $dates = $event->dateOptions->map(function (EventDateOption $option): string {
            $yes = $option->votes->filter(fn (EventDateVote $vote): bool => $vote->answer === DateVote::Yes)->count();
            $ifNeedBe = $option->votes->filter(fn (EventDateVote $vote): bool => $vote->answer === DateVote::IfNeedBe)->count();

            return '• <t:'.$option->starts_at->getTimestamp().":F> ({$yes} yes, {$ifNeedBe} if need be)";
        })->implode("\n");

        return "Not decided yet: press **Vote on dates**.\n{$dates}";
    }

    /**
     * @return array<string, mixed>
     */
    private static function voteOnDatesRow(Event $event): array
    {
        return [
            'type' => self::ACTION_ROW,
            'components' => [[
                'type' => self::BUTTON,
                'style' => self::PRIMARY,
                'label' => 'Vote on dates',
                'custom_id' => EventCustomId::dates($event->id),
                'disabled' => $event->isCancelled(),
            ]],
        ];
    }

    /**
     * The native Discord event (external, at the event's location).
     *
     * @return array<string, mixed>
     */
    public static function scheduledEvent(Event $event): array
    {
        $endsAt = $event->ends_at ?? $event->starts_at->copy()->addHours(self::ASSUMED_HOURS);
        $payload = [
            'name' => Str::limit($event->title, 100, ''),
            'privacy_level' => self::GUILD_ONLY,
            'entity_type' => self::EXTERNAL,
            'scheduled_start_time' => $event->starts_at->toIso8601String(),
            'scheduled_end_time' => $endsAt->toIso8601String(),
            'entity_metadata' => ['location' => Str::limit($event->location ?? 'See the website', 100, '')],
            'description' => Str::limit(trim(($event->description ?? '')."\n\n".route('events.show', $event)), 1000, ''),
        ];

        return $event->isCancelled() ? [...$payload, 'status' => self::CANCELLED] : $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private static function button(Event $event, RsvpStatus $status, int $style): array
    {
        return [
            'type' => self::BUTTON,
            'style' => $style,
            'label' => $status->label(),
            'custom_id' => EventCustomId::rsvp($event->id, $status),
            'disabled' => $event->isCancelled() || ($status !== RsvpStatus::Declined && $event->rsvpClosed()),
        ];
    }
}
