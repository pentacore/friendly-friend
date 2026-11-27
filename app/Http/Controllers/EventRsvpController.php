<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RsvpStatus;
use App\Http\Requests\Events\RsvpRequest;
use App\Models\Event;
use App\Models\User;
use App\Planning\Actions\Questions\SaveAnswers;
use App\Planning\Actions\RecordRsvp;
use Illuminate\Http\RedirectResponse;
use LogicException;

/**
 * Your answer to an event, from the event page, with your answers to its questions.
 */
final class EventRsvpController extends Controller
{
    public function update(RsvpRequest $request, Event $event, RecordRsvp $record, SaveAnswers $saveAnswers): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('RSVPs require a logged-in user.');
        }

        $status = $request->enum('status', RsvpStatus::class) ?? RsvpStatus::Going;

        $record->handle(
            $event,
            $user->discordUser,
            $status,
            $request->integer('guests'),
            $request->filled('note') ? $request->string('note')->trim()->value() : null,
            $request->boolean('needs_ride'),
        );

        if ($status !== RsvpStatus::Declined && $request->has('answers')) {
            $saveAnswers->handle($event, $user->discordUser, $this->answers($request->array('answers')));
        }

        return back();
    }

    /**
     * @param  array<mixed>  $answers
     * @return array<int, string|null>
     */
    private function answers(array $answers): array
    {
        $clean = [];

        foreach ($answers as $questionId => $value) {
            if (is_numeric($questionId)) {
                $clean[(int) $questionId] = is_string($value) ? $value : null;
            }
        }

        return $clean;
    }
}
