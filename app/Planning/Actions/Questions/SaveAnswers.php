<?php

declare(strict_types=1);

namespace App\Planning\Actions\Questions;

use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventQuestionAnswer;

/**
 * Saves someone's answers to an event's questions. An empty answer clears
 * it; answers to questions of other events, or values a question doesn't
 * accept, are ignored.
 */
final readonly class SaveAnswers
{
    /**
     * @param  array<int, string|null>  $answers  question ID => value
     */
    public function handle(Event $event, DiscordUser $member, array $answers): void
    {
        $questions = $event->questions()->whereIn('id', array_keys($answers))->get();

        foreach ($questions as $question) {
            $value = trim((string) ($answers[$question->id] ?? ''));
            $allowed = $question->allowedValues();

            if ($value === '' || ($allowed !== null && ! in_array($value, $allowed, true))) {
                EventQuestionAnswer::query()->where('event_question_id', $question->id)->where('discord_user_id', $member->id)->delete();

                continue;
            }

            EventQuestionAnswer::query()->updateOrCreate(
                ['event_question_id' => $question->id, 'discord_user_id' => $member->id],
                ['value' => mb_substr($value, 0, 500)],
            );
        }

        if ($questions->isNotEmpty()) {
            broadcast(new EventChanged($event->id, EventChanged::OVERVIEW));
        }
    }
}
