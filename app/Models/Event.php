<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\RsvpStatus;
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
 * @property Carbon|null $rsvp_by when answers close (Can't stays open)
 * @property bool $date_polling the date is still being voted on (starts_at holds the earliest candidate)
 * @property string $timezone
 * @property EventStatus $status
 * @property string $currency
 * @property string|null $announce_channel_id
 * @property string|null $discord_message_id
 * @property string|null $discord_scheduled_event_id
 * @property Carbon|null $reminded_day_at
 * @property Carbon|null $reminded_hours_at
 * @property Carbon|null $reminded_rsvp_at
 * @property Carbon|null $rsvp_closed_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Guild $guild
 * @property-read DiscordUser $createdBy
 */
#[Fillable(['guild_id', 'created_by_discord_user_id', 'title', 'description', 'location', 'location_url', 'starts_at', 'ends_at', 'rsvp_by', 'timezone', 'status', 'currency', 'announce_channel_id', 'discord_message_id', 'discord_scheduled_event_id', 'reminded_day_at', 'reminded_hours_at', 'reminded_rsvp_at', 'rsvp_closed_synced_at', 'date_polling'])]
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

    /**
     * @return HasMany<EventBringItem, $this>
     */
    public function bringItems(): HasMany
    {
        return $this->hasMany(EventBringItem::class);
    }

    /**
     * @return HasMany<EventShoppingItem, $this>
     */
    public function shoppingItems(): HasMany
    {
        return $this->hasMany(EventShoppingItem::class);
    }

    /**
     * @return HasMany<EventMeal, $this>
     */
    public function meals(): HasMany
    {
        return $this->hasMany(EventMeal::class);
    }

    /**
     * @return HasMany<EventRide, $this>
     */
    public function rides(): HasMany
    {
        return $this->hasMany(EventRide::class);
    }

    /**
     * @return HasMany<EventExpense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(EventExpense::class);
    }

    /**
     * @return HasMany<EventPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(EventPayment::class);
    }

    /**
     * Who can be in the costs: anyone going or maybe, the hosts, and
     * anyone already in an expense or payment.
     *
     * @return list<int> discord_users.id
     */
    public function costPeopleIds(): array
    {
        $ids = [
            $this->created_by_discord_user_id,
            ...$this->hosts()->pluck('discord_users.id')->all(),
            ...$this->rsvps()->where('status', '!=', RsvpStatus::Declined)->pluck('discord_user_id')->all(),
            ...$this->expenses()->pluck('paid_by_discord_user_id')->all(),
            ...EventExpenseShare::query()->whereIn('event_expense_id', $this->expenses()->select('id'))->pluck('discord_user_id')->all(),
            ...$this->payments()->pluck('from_discord_user_id')->all(),
            ...$this->payments()->pluck('to_discord_user_id')->all(),
        ];

        return array_values(array_unique(array_filter(array_map(intval(...), array_filter($ids, is_numeric(...))))));
    }

    /**
     * @return HasMany<EventQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(EventQuestion::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<EventDateOption, $this>
     */
    public function dateOptions(): HasMany
    {
        return $this->hasMany(EventDateOption::class)->orderBy('starts_at');
    }

    /**
     * Past the "answer by" time: Going and Maybe are closed, Can't is not.
     */
    public function rsvpClosed(): bool
    {
        return $this->rsvp_by !== null && $this->rsvp_by->isPast();
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
            'reminded_day_at' => 'datetime',
            'reminded_hours_at' => 'datetime',
            'rsvp_by' => 'datetime',
            'date_polling' => 'boolean',
            'reminded_rsvp_at' => 'datetime',
            'rsvp_closed_synced_at' => 'datetime',
        ];
    }
}
