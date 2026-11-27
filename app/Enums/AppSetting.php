<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A limit owners can tune on the Settings page (`App\Access\AppSettings`),
 * with its default and the bounds it is clamped to when read.
 */
enum AppSetting: string
{
    case ReminderFirstHours = 'reminder_first_hours';
    case ReminderSecondHours = 'reminder_second_hours';
    case MaybeNudgeHours = 'maybe_nudge_hours';
    case DraftTtlMinutes = 'draft_ttl_minutes';
    case TopListSize = 'top_list_size';
    case TopListMinMatchups = 'top_list_min_matchups';

    public function default(): int
    {
        return match ($this) {
            self::ReminderFirstHours, self::MaybeNudgeHours => 24,
            self::ReminderSecondHours => 2,
            self::DraftTtlMinutes => 14,
            self::TopListSize => 10,
            self::TopListMinMatchups => 3,
        };
    }

    public function min(): int
    {
        return match ($this) {
            self::TopListSize => 3,
            default => 1,
        };
    }

    public function max(): int
    {
        return match ($this) {
            self::ReminderFirstHours, self::MaybeNudgeHours => 168,
            self::ReminderSecondHours => 48,
            // Discord lets the bot edit an interaction reply for 15 minutes; the preview must expire inside that.
            self::DraftTtlMinutes => 14,
            self::TopListSize, self::TopListMinMatchups => 50,
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::ReminderFirstHours, self::ReminderSecondHours, self::MaybeNudgeHours => 'Events',
            self::DraftTtlMinutes => 'Quotes in Discord',
            self::TopListSize, self::TopListMinMatchups => 'Top lists',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ReminderFirstHours => 'First reminder',
            self::ReminderSecondHours => 'Second reminder',
            self::MaybeNudgeHours => 'Nudge people on Maybe',
            self::DraftTtlMinutes => 'Drafts expire after',
            self::TopListSize => 'Quotes per top list',
            self::TopListMinMatchups => 'This-or-that rounds before a quote is ranked',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::ReminderFirstHours, self::ReminderSecondHours, self::MaybeNudgeHours => 'hours',
            self::DraftTtlMinutes => 'minutes',
            self::TopListSize => 'quotes',
            self::TopListMinMatchups => 'rounds',
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::ReminderFirstHours => 'How long before an event starts the bot DMs everyone going for the first time.',
            self::ReminderSecondHours => 'The last reminder. It must come after the first one.',
            self::MaybeNudgeHours => 'How long before the "answer by" time the bot asks people on Maybe to decide.',
            self::DraftTtlMinutes => 'An unsaved quote preview expires after this long. Discord allows at most 14.',
            self::TopListSize => 'How many quotes each top list shows.',
            self::TopListMinMatchups => 'A quote needs this many this-or-that rounds before its rating counts.',
        };
    }
}
