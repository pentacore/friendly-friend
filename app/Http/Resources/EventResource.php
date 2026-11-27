<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Access\AccessSnapshot;
use App\Enums\EventStatus;
use App\Enums\RsvpStatus;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Planning\EventRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * An event as lists and the event page show it. Load `guild`, `createdBy`,
 * `hosts` and `rsvps`.
 */
final class EventResource extends JsonResource
{
    public function __construct(private readonly Event $event)
    {
        parent::__construct($event);
    }

    /**
     * @return array{
     *     id: int,
     *     guild: array{id: int, name: string},
     *     title: string,
     *     description: string|null,
     *     location: string|null,
     *     location_url: string|null,
     *     starts_at: string,
     *     ends_at: string|null,
     *     rsvp_by: string|null,
     *     rsvp_closed: bool,
     *     date_polling: bool,
     *     timezone: string,
     *     status: EventStatus,
     *     currency: string,
     *     created_by: array{id: int, name: string, avatar_url: string},
     *     hosts: list<array{id: int, name: string, avatar_url: string}>,
     *     counts: array{going: int, maybe: int, declined: int, guests: int},
     *     my_rsvp: array{status: RsvpStatus, guests: int, note: string|null, needs_ride: bool}|null,
     *     can: array{edit: bool, participate: bool},
     * }
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $event = $this->event;
        $access = AccessSnapshot::forRequest($request);
        $mine = $access instanceof AccessSnapshot ? $event->rsvps->firstWhere('discord_user_id', $access->discordUserId) : null;
        $count = fn (RsvpStatus $status): int => $event->rsvps->filter(fn (EventRsvp $rsvp): bool => $rsvp->status === $status)->count();

        return [
            'id' => $event->id,
            'guild' => ['id' => $event->guild->id, 'name' => $event->guild->name],
            'title' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'location_url' => $event->location_url,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'rsvp_by' => $event->rsvp_by?->toIso8601String(),
            'rsvp_closed' => $event->rsvpClosed(),
            'date_polling' => $event->date_polling,
            'timezone' => $event->timezone,
            'status' => $event->status,
            'currency' => $event->currency,
            'created_by' => $event->createdBy->toMember(),
            'hosts' => array_values($event->hosts->map(fn (DiscordUser $host): array => $host->toMember())->all()),
            'counts' => [
                'going' => $count(RsvpStatus::Going),
                'maybe' => $count(RsvpStatus::Maybe),
                'declined' => $count(RsvpStatus::Declined),
                'guests' => $event->rsvps->filter(fn (EventRsvp $rsvp): bool => $rsvp->status === RsvpStatus::Going)->sum(fn (EventRsvp $rsvp): int => $rsvp->guests),
            ],
            'my_rsvp' => $mine instanceof EventRsvp
                ? ['status' => $mine->status, 'guests' => $mine->guests, 'note' => $mine->note, 'needs_ride' => $mine->needs_ride]
                : null,
            'can' => [
                'edit' => $access instanceof AccessSnapshot && EventRules::canEdit($access, $event),
                'participate' => $access instanceof AccessSnapshot && EventRules::canParticipate($access, $event),
            ],
        ];
    }
}
