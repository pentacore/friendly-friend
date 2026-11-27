<?php

declare(strict_types=1);

namespace App\Planning;

use App\Access\AccessSnapshot;
use App\Enums\RideDirection;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\EventRide;
use App\Models\EventRidePassenger;
use App\Models\EventRsvp;

/**
 * The Transport tab of an event: rides with their seats, and who still
 * needs a ride there.
 *
 * @phpstan-type Member array{id: int, name: string, avatar_url: string}
 */
final readonly class TransportPlan
{
    private const string MEMBER = '{ id: number; name: string; avatar_url: string }';

    /** Typefinder shape of {@see self::for()}. */
    public const string TYPE = '{ rides: { id: number; direction: '.RideDirection::class.'; driver: '.self::MEMBER.'; departs_from: string | null; departs_at: string | null; seats: number; note: string | null; passengers: { member: '.self::MEMBER.'; pickup_note: string | null }[]; can_manage: boolean }[]; needs_ride: '.self::MEMBER.'[] }';

    /**
     * @return array{
     *     rides: list<array{id: int, direction: RideDirection, driver: Member, departs_from: string|null, departs_at: string|null, seats: int, note: string|null, passengers: list<array{member: Member, pickup_note: string|null}>, can_manage: bool}>,
     *     needs_ride: list<Member>,
     * }
     */
    public static function for(Event $event, AccessSnapshot $access): array
    {
        $event->load(['rides.driver', 'rides.passengers.discordUser', 'rsvps.discordUser', 'hosts']);
        $organiser = EventRules::canEdit($access, $event);

        $seatedThere = $event->rides
            ->filter(fn (EventRide $ride): bool => $ride->direction->covers(RideDirection::To))
            ->flatMap(fn (EventRide $ride): array => [$ride->driver_discord_user_id, ...$ride->passengers->pluck('discord_user_id')->all()])
            ->all();

        return [
            'rides' => array_values($event->rides
                ->sortBy(fn (EventRide $ride): string => array_search($ride->direction, RideDirection::cases(), true).($ride->departs_at?->toIso8601String() ?? 'z'))
                ->map(fn (EventRide $ride): array => [
                    'id' => $ride->id,
                    'direction' => $ride->direction,
                    'driver' => $ride->driver->toMember(),
                    'departs_from' => $ride->departs_from,
                    'departs_at' => $ride->departs_at?->toIso8601String(),
                    'seats' => $ride->seats,
                    'note' => $ride->note,
                    'passengers' => array_values($ride->passengers->map(fn (EventRidePassenger $passenger): array => [
                        'member' => $passenger->discordUser->toMember(),
                        'pickup_note' => $passenger->pickup_note,
                    ])->all()),
                    'can_manage' => $organiser || $ride->driver_discord_user_id === $access->discordUserId,
                ])->all()),
            'needs_ride' => array_values($event->rsvps
                ->filter(fn (EventRsvp $rsvp): bool => $rsvp->needs_ride
                    && $rsvp->status !== RsvpStatus::Declined
                    && ! in_array($rsvp->discord_user_id, $seatedThere, true))
                ->map(fn (EventRsvp $rsvp): array => $rsvp->discordUser->toMember())
                ->all()),
        ];
    }
}
