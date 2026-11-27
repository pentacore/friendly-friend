<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DateVote;
use App\Http\Requests\Events\DateOptionRequest;
use App\Http\Requests\Events\DateVoteRequest;
use App\Models\Event;
use App\Models\EventDateOption;
use App\Models\User;
use App\Planning\Actions\DatePoll\AddDateOption;
use App\Planning\Actions\DatePoll\CastDateVote;
use App\Planning\Actions\DatePoll\ChooseDate;
use App\Planning\Actions\DatePoll\RemoveDateOption;
use App\Planning\Exceptions\LastDateOptionException;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use LogicException;

/**
 * The date vote: proposing and withdrawing dates, voting, and choosing one.
 */
final class EventDatePollController extends Controller
{
    public function store(DateOptionRequest $request, Event $event, AddDateOption $add): RedirectResponse
    {
        $add->handle($event, $request->local($event, 'starts_at') ?? CarbonImmutable::now(), $request->local($event, 'ends_at'));

        return back();
    }

    public function destroy(Event $event, EventDateOption $dateOption, RemoveDateOption $remove): RedirectResponse
    {
        Gate::authorize('update', $event);

        try {
            $remove->handle($event, $dateOption);
        } catch (LastDateOptionException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('That’s the last proposed date. Choose it instead.')]);
        }

        return back();
    }

    public function vote(DateVoteRequest $request, Event $event, EventDateOption $dateOption, CastDateVote $cast): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('Voting requires a logged-in user.');
        }

        $cast->handle($dateOption, $user->discordUser, $request->enum('answer', DateVote::class));

        return back();
    }

    public function choose(Request $request, Event $event, EventDateOption $dateOption, ChooseDate $choose): RedirectResponse
    {
        Gate::authorize('update', $event);
        abort_unless($event->date_polling, 409);

        $user = $request->user();
        $choose->handle($event, $dateOption, $user instanceof User ? $user->discordUser : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Date chosen. Everyone who said yes is going.')]);

        return back();
    }
}
