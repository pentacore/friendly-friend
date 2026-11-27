<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\QuestionKind;
use App\Http\Requests\Events\QuestionRequest;
use App\Models\Event;
use App\Models\EventQuestion;
use App\Planning\Actions\Questions\AddQuestion;
use App\Planning\Actions\Questions\RemoveQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Organisers add and remove an event's questions.
 */
final class EventQuestionController extends Controller
{
    public function store(QuestionRequest $request, Event $event, AddQuestion $add): RedirectResponse
    {
        $add->handle($event, $request->string('prompt')->trim()->value(), $request->enum('kind', QuestionKind::class) ?? QuestionKind::YesNo, $request->options());

        return back();
    }

    public function destroy(Event $event, EventQuestion $question, RemoveQuestion $remove): RedirectResponse
    {
        Gate::authorize('update', $event);
        $remove->handle($question);

        return back();
    }
}
