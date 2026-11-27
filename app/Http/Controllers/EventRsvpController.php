<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RsvpStatus;
use App\Http\Requests\Events\RsvpRequest;
use App\Models\Event;
use App\Models\User;
use App\Planning\Actions\RecordRsvp;
use Illuminate\Http\RedirectResponse;
use LogicException;

/**
 * Your answer to an event, from the event page.
 */
final class EventRsvpController extends Controller
{
    public function update(RsvpRequest $request, Event $event, RecordRsvp $record): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('RSVPs require a logged-in user.');
        }

        $record->handle(
            $event,
            $user->discordUser,
            $request->enum('status', RsvpStatus::class) ?? RsvpStatus::Going,
            $request->integer('guests'),
            $request->filled('note') ? $request->string('note')->trim()->value() : null,
            $request->boolean('needs_ride'),
        );

        return back();
    }
}
