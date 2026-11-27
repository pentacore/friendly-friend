<?php

declare(strict_types=1);

namespace App\Planning\Actions;

use App\Enums\RsvpStatus;
use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\Guild;
use App\Models\GuildMember;
use App\Planning\Data\EventDetails;
use Illuminate\Support\Facades\DB;

/**
 * Creates an event (the creator is going) or updates one. Co-hosts must be
 * members of the event's server.
 */
final readonly class SaveEvent
{
    public function __construct(private RecordRsvp $recordRsvp) {}

    public function create(Guild $guild, DiscordUser $creator, EventDetails $details): Event
    {
        $event = DB::transaction(function () use ($guild, $creator, $details): Event {
            $event = Event::query()->create([
                'guild_id' => $guild->id,
                'created_by_discord_user_id' => $creator->id,
                'currency' => 'SEK',
                ...$this->attributes($details),
            ]);

            $this->syncHosts($event, $details->hostIds);

            return $event;
        });

        $this->recordRsvp->handle($event, $creator, RsvpStatus::Going);

        return $event;
    }

    public function update(Event $event, EventDetails $details): Event
    {
        DB::transaction(function () use ($event, $details): void {
            $event->update($this->attributes($details));
            $this->syncHosts($event, $details->hostIds);
        });

        broadcast(new EventChanged($event->id, EventChanged::OVERVIEW));

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(EventDetails $details): array
    {
        return [
            'title' => $details->title,
            'description' => $details->description,
            'location' => $details->location,
            'location_url' => $details->locationUrl,
            'starts_at' => $details->startsAt,
            'ends_at' => $details->endsAt,
            'timezone' => $details->timezone,
        ];
    }

    /**
     * @param  list<int>  $hostIds
     */
    private function syncHosts(Event $event, array $hostIds): void
    {
        $members = GuildMember::query()
            ->where('guild_id', $event->guild_id)
            ->where('in_guild', true)
            ->whereIn('discord_user_id', $hostIds)
            ->where('discord_user_id', '!=', $event->created_by_discord_user_id)
            ->pluck('discord_user_id');

        $event->hosts()->sync($members->all());
    }
}
