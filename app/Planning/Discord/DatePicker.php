<?php

declare(strict_types=1);

namespace App\Planning\Discord;

use App\Discord\Interactions\EventCustomId;
use App\Enums\DateVote;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventDateOption;
use App\Models\EventDateVote;

/**
 * The private date picker shown after "Vote on dates": two select menus
 * (dates you can make, dates that work if need be) pre-filled with your
 * votes. Pure: load `dateOptions.votes` first.
 */
final class DatePicker
{
    private const int ACTION_ROW = 1;

    private const int STRING_SELECT = 3;

    /**
     * @return array<string, mixed>
     */
    public static function message(Event $event, DiscordUser $member, ?string $notice = null): array
    {
        $mine = fn (EventDateOption $option): ?DateVote => $option->votes->first(fn (EventDateVote $vote): bool => $vote->discord_user_id === $member->id)?->answer;
        $count = $event->dateOptions->count();

        return [
            'content' => ($notice !== null ? "{$notice}\n\n" : '')
                ."Which dates work for **{$event->title}**? Dates you don't pick count as no. All dates and who can make them: ".route('events.show', $event),
            'components' => [
                self::select($event, EventCustomId::datesYes($event->id), 'Dates I can make', $count, fn (EventDateOption $option): bool => $mine($option) === DateVote::Yes),
                self::select($event, EventCustomId::datesMaybe($event->id), 'Dates that work if need be', $count, fn (EventDateOption $option): bool => $mine($option) === DateVote::IfNeedBe),
            ],
        ];
    }

    /**
     * A date as a select option label, in the event's timezone ("Fri 5 Dec, 18:00").
     */
    public static function label(Event $event, EventDateOption $option): string
    {
        $start = $option->starts_at->copy()->setTimezone($event->timezone);
        $end = $option->ends_at?->copy()->setTimezone($event->timezone);

        return $start->format('D j M, H:i').($end !== null ? '–'.($end->isSameDay($start) ? $end->format('H:i') : $end->format('D j M, H:i')) : '');
    }

    /**
     * @param  callable(EventDateOption): bool  $selected
     * @return array<string, mixed>
     */
    private static function select(Event $event, string $customId, string $placeholder, int $count, callable $selected): array
    {
        return [
            'type' => self::ACTION_ROW,
            'components' => [[
                'type' => self::STRING_SELECT,
                'custom_id' => $customId,
                'placeholder' => $placeholder,
                'min_values' => 0,
                'max_values' => max(1, $count),
                'options' => array_values($event->dateOptions->map(fn (EventDateOption $option): array => [
                    'label' => self::label($event, $option),
                    'value' => (string) $option->id,
                    'default' => $selected($option),
                ])->all()),
            ]],
        ];
    }
}
