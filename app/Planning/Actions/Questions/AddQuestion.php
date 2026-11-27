<?php

declare(strict_types=1);

namespace App\Planning\Actions\Questions;

use App\Enums\QuestionKind;
use App\Events\EventChanged;
use App\Models\Event;
use App\Models\EventQuestion;

/**
 * Organisers ask everyone coming something ("Will you eat crayfish?").
 */
final readonly class AddQuestion
{
    /**
     * @param  list<string>  $options  for QuestionKind::Choice
     */
    public function handle(Event $event, string $prompt, QuestionKind $kind, array $options = []): EventQuestion
    {
        $question = $event->questions()->create([
            'prompt' => $prompt,
            'kind' => $kind,
            'options' => $kind === QuestionKind::Choice ? array_values(array_unique($options)) : [],
            'position' => $event->questions()->count() + 1,
        ]);

        broadcast(new EventChanged($event->id, EventChanged::OVERVIEW));

        return $question;
    }
}
