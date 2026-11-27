<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Someone with a seat in a ride, and where to pick them up.
 *
 * @property int $id
 * @property int $event_ride_id
 * @property int $discord_user_id
 * @property string|null $pickup_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DiscordUser $discordUser
 */
#[Fillable(['event_ride_id', 'discord_user_id', 'pickup_note'])]
class EventRidePassenger extends Model
{
    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function discordUser(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class);
    }
}
