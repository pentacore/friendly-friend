<?php

declare(strict_types=1);

namespace App\Planning\Actions\DatePoll;

use App\Enums\DateVote;
use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\EventDateOption;
use App\Models\EventDateVote;

/**
 * Yes / if need be / no for one proposed date; null takes the vote back.
 */
final readonly class CastDateVote
{
    public function handle(EventDateOption $option, DiscordUser $member, ?DateVote $answer): void
    {
        if (! $answer instanceof DateVote) {
            EventDateVote::query()->where('event_date_option_id', $option->id)->where('discord_user_id', $member->id)->delete();
        } else {
            EventDateVote::query()->updateOrCreate(
                ['event_date_option_id' => $option->id, 'discord_user_id' => $member->id],
                ['answer' => $answer],
            );
        }

        broadcast(new EventChanged($option->event_id, EventChanged::OVERVIEW));
    }
}
