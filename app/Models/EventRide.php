<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RideDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A car going to and/or from an event, with free seats people can take.
 *
 * @property int $id
 * @property int $event_id
 * @property int $driver_discord_user_id
 * @property RideDirection $direction
 * @property string|null $departs_from
 * @property Carbon|null $departs_at
 * @property int $seats
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DiscordUser $driver
 * @property-read Collection<int, EventRidePassenger> $passengers
 */
#[Fillable(['event_id', 'driver_discord_user_id', 'direction', 'departs_from', 'departs_at', 'seats', 'note'])]
class EventRide extends Model
{
    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class, 'driver_discord_user_id');
    }

    /**
     * @return HasMany<EventRidePassenger, $this>
     */
    public function passengers(): HasMany
    {
        return $this->hasMany(EventRidePassenger::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => RideDirection::class,
            'departs_at' => 'datetime',
            'seats' => 'integer',
        ];
    }
}
