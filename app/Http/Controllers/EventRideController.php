<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RideDirection;
use App\Http\Requests\Events\JoinRideRequest;
use App\Http\Requests\Events\OfferRideRequest;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventRide;
use App\Models\User;
use App\Planning\Actions\Transport\CancelRide;
use App\Planning\Actions\Transport\JoinRide;
use App\Planning\Actions\Transport\LeaveRide;
use App\Planning\Actions\Transport\OfferRide;
use App\Planning\Exceptions\RideFullException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use LogicException;

/**
 * Writes on an event's Transport tab: offering, taking and leaving seats.
 */
final class EventRideController extends Controller
{
    public function store(OfferRideRequest $request, Event $event, OfferRide $offer): RedirectResponse
    {
        $offer->handle(
            $event,
            $this->member($request),
            $request->enum('direction', RideDirection::class) ?? RideDirection::Both,
            $request->integer('seats', 1),
            $request->filled('departs_from') ? $request->string('departs_from')->trim()->value() : null,
            $request->departsAt($event),
            $request->filled('note') ? $request->string('note')->trim()->value() : null,
        );

        return back();
    }

    public function destroy(Request $request, Event $event, EventRide $ride, CancelRide $cancel): RedirectResponse
    {
        $this->authorizeManage($request, $event, $ride);
        $cancel->handle($ride);

        return back();
    }

    public function join(JoinRideRequest $request, Event $event, EventRide $ride, JoinRide $join): RedirectResponse
    {
        try {
            $join->handle($ride, $this->member($request), $request->filled('pickup_note') ? $request->string('pickup_note')->trim()->value() : null);
        } catch (RideFullException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('That ride is full.')]);
        }

        return back();
    }

    /**
     * Leave yourself, or (driver, organisers) remove the passenger given as
     * `discord_user_id`.
     */
    public function leave(Request $request, Event $event, EventRide $ride, LeaveRide $leave): RedirectResponse
    {
        $me = $this->member($request);
        $passenger = $request->filled('discord_user_id') ? DiscordUser::query()->findOrFail($request->integer('discord_user_id')) : $me;

        if ($passenger->id !== $me->id) {
            $this->authorizeManage($request, $event, $ride);
        }

        $leave->handle($ride, $passenger);

        return back();
    }

    private function authorizeManage(Request $request, Event $event, EventRide $ride): void
    {
        abort_unless($ride->driver_discord_user_id === $this->member($request)->id || Gate::allows('update', $event), 403);
    }

    private function member(Request $request): DiscordUser
    {
        $user = $request->user();

        return $user instanceof User ? $user->discordUser : throw new LogicException('Rides require a logged-in user.');
    }
}
