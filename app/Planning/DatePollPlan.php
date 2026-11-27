<?php

declare(strict_types=1);

namespace App\Planning;

use App\Access\AccessSnapshot;
use App\Enums\DateVote;
use App\Models\Event;
use App\Models\EventDateOption;
use App\Models\EventDateVote;

/**
 * The date vote of an event: each proposed date with its tallies, who
 * voted what (open to everyone, like any scheduling poll), your own vote,
 * and which date currently works best (most yes, then most if need be).
 *
 * @phpstan-type Member array{id: int, name: string, avatar_url: string}
 */
final readonly class DatePollPlan
{
    private const string MEMBER = '{ id: number; name: string; avatar_url: string }';

    /** Typefinder shape of {@see self::for()}. */
    public const string TYPE = '{ id: number; starts_at: string; ends_at: string | null; yes: number; if_need_be: number; no: number; mine: '.DateVote::class.' | null; votes: { member: '.self::MEMBER.'; answer: '.DateVote::class.' }[]; best: boolean }[]';

    /**
     * @return list<array{id: int, starts_at: string, ends_at: string|null, yes: int, if_need_be: int, no: int, mine: DateVote|null, votes: list<array{member: Member, answer: DateVote}>, best: bool}>
     */
    public static function for(Event $event, AccessSnapshot $access): array
    {
        if (! $event->date_polling) {
            return [];
        }

        $event->load('dateOptions.votes.discordUser');
        $count = fn (EventDateOption $option, DateVote $answer): int => $option->votes->filter(fn (EventDateVote $vote): bool => $vote->answer === $answer)->count();
        $score = fn (EventDateOption $option): int => $count($option, DateVote::Yes) * 1000 + $count($option, DateVote::IfNeedBe);
        $bestScore = $event->dateOptions->max($score);

        return array_values($event->dateOptions->map(fn (EventDateOption $option): array => [
            'id' => $option->id,
            'starts_at' => $option->starts_at->toIso8601String(),
            'ends_at' => $option->ends_at?->toIso8601String(),
            'yes' => $count($option, DateVote::Yes),
            'if_need_be' => $count($option, DateVote::IfNeedBe),
            'no' => $count($option, DateVote::No),
            'mine' => $option->votes->firstWhere('discord_user_id', $access->discordUserId)?->answer,
            'votes' => array_values($option->votes->map(fn (EventDateVote $vote): array => [
                'member' => $vote->discordUser->toMember(),
                'answer' => $vote->answer,
            ])->all()),
            'best' => $bestScore > 0 && $score($option) === $bestScore,
        ])->all());
    }
}
