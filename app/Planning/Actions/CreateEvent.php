<?php

declare(strict_types=1);

namespace App\Planning\Actions;

use App\Enums\RsvpStatus;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\Guild;
use App\Planning\Data\EventDetails;
use App\Planning\EventAttributes;
use Illuminate\Support\Facades\DB;

/**
 * Creates an event on a server. The creator is going.
 */
final readonly class CreateEvent
{
    public function __construct(private EventAttributes $attributes, private RecordRsvp $recordRsvp) {}

    public function handle(Guild $guild, DiscordUser $creator, EventDetails $details): Event
    {
        $event = DB::transaction(function () use ($guild, $creator, $details): Event {
            $event = Event::query()->create([
                'guild_id' => $guild->id,
                'created_by_discord_user_id' => $creator->id,
                'currency' => 'SEK',
                ...$this->attributes->columns($details),
            ]);

            $this->attributes->syncHosts($event, $details->hostIds);

            return $event;
        });

        $this->recordRsvp->handle($event, $creator, RsvpStatus::Going);

        return $event;
    }
}
