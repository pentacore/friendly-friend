<?php

declare(strict_types=1);

namespace App\Planning\Actions\Questions;

use App\Events\EventChanged;
use App\Models\EventQuestion;

/**
 * Deletes a question and its answers.
 */
final readonly class RemoveQuestion
{
    public function handle(EventQuestion $question): void
    {
        $question->delete();

        broadcast(new EventChanged($question->event_id, EventChanged::OVERVIEW));
    }
}
