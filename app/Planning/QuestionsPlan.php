<?php

declare(strict_types=1);

namespace App\Planning;

use App\Access\AccessSnapshot;
use App\Enums\QuestionKind;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\EventQuestion;
use App\Models\EventQuestionAnswer;
use App\Models\EventRsvp;

/**
 * An event's questions with your own answers and the results. Only
 * answers from people going or maybe count. Everyone sees the counts;
 * organisers also see who answered what, including free text.
 *
 * @phpstan-type Member array{id: int, name: string, avatar_url: string}
 */
final readonly class QuestionsPlan
{
    private const string MEMBER = '{ id: number; name: string; avatar_url: string }';

    /** Typefinder shape of {@see self::for()}. */
    public const string TYPE = '{ id: number; prompt: string; kind: '.QuestionKind::class.'; options: string[]; mine: string | null; answered: number; counts: { value: string; total: number }[]; answers: { member: '.self::MEMBER.'; value: string }[] | null }[]';

    /**
     * @return list<array{id: int, prompt: string, kind: QuestionKind, options: list<string>, mine: string|null, answered: int, counts: list<array{value: string, total: int}>, answers: list<array{member: Member, value: string}>|null}>
     */
    public static function for(Event $event, AccessSnapshot $access): array
    {
        $event->load(['questions.answers.discordUser', 'rsvps', 'hosts']);
        $organiser = EventRules::canEdit($access, $event);
        $coming = $event->rsvps
            ->filter(fn (EventRsvp $rsvp): bool => $rsvp->status !== RsvpStatus::Declined)
            ->pluck('discord_user_id')
            ->all();

        return array_values($event->questions->map(function (EventQuestion $question) use ($access, $organiser, $coming): array {
            $answers = $question->answers->filter(fn (EventQuestionAnswer $answer): bool => in_array($answer->discord_user_id, $coming, true));
            $values = $question->allowedValues();

            return [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'kind' => $question->kind,
                'options' => $question->options,
                'mine' => $question->answers->firstWhere('discord_user_id', $access->discordUserId)?->value,
                'answered' => $answers->count(),
                'counts' => $values === null ? [] : array_map(fn (string $value): array => [
                    'value' => $value,
                    'total' => $answers->filter(fn (EventQuestionAnswer $answer): bool => $answer->value === $value)->count(),
                ], $values),
                'answers' => $organiser ? array_values($answers->map(fn (EventQuestionAnswer $answer): array => [
                    'member' => $answer->discordUser->toMember(),
                    'value' => $answer->value,
                ])->all()) : null,
            ];
        })->all());
    }
}
