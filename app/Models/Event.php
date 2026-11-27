<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A get-together planned on one server: when and where, who comes, and
 * (in its own tables) food, rides and costs.
 *
 * @property int $id
 * @property int $guild_id
 * @property int $created_by_discord_user_id
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property string|null $location_url
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property string $timezone
 * @property EventStatus $status
 * @property string $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Guild $guild
 * @property-read DiscordUser $createdBy
 */
#[Fillable(['guild_id', 'created_by_discord_user_id', 'title', 'description', 'location', 'location_url', 'starts_at', 'ends_at', 'timezone', 'status', 'currency'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Guild, $this>
     */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class, 'created_by_discord_user_id');
    }

    /**
     * Co-hosts, who may edit the event like its creator.
     *
     * @return BelongsToMany<DiscordUser, $this>
     */
    public function hosts(): BelongsToMany
    {
        return $this->belongsToMany(DiscordUser::class, 'event_hosts');
    }

    /**
     * @return HasMany<EventRsvp, $this>
     */
    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    public function isCancelled(): bool
    {
        return $this->status === EventStatus::Cancelled;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => EventStatus::class,
        ];
    }
}
