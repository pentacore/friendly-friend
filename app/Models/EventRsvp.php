<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RsvpStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's answer to an event, with how many guests they bring.
 *
 * @property int $id
 * @property int $event_id
 * @property int $discord_user_id
 * @property RsvpStatus $status
 * @property int $guests
 * @property string|null $note
 * @property bool $needs_ride
 * @property bool $from_discord_interest made by "Interested" on the native Discord event
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DiscordUser $discordUser
 */
#[Fillable(['event_id', 'discord_user_id', 'status', 'guests', 'note', 'needs_ride', 'from_discord_interest'])]
class EventRsvp extends Model
{
    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function discordUser(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RsvpStatus::class,
            'guests' => 'integer',
            'needs_ride' => 'boolean',
            'from_discord_interest' => 'boolean',
        ];
    }
}
