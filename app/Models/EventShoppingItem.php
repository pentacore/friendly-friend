<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something to buy for an event; ticked off when someone bought it.
 *
 * @property int $id
 * @property int $event_id
 * @property string $label
 * @property string|null $quantity
 * @property int $added_by_discord_user_id
 * @property int|null $checked_by_discord_user_id
 * @property Carbon|null $checked_at
 * @property int|null $expense_id the expense that paid for it
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DiscordUser $addedBy
 * @property-read DiscordUser|null $checkedBy
 * @property-read EventExpense|null $expense
 */
#[Fillable(['event_id', 'label', 'quantity', 'added_by_discord_user_id', 'checked_by_discord_user_id', 'checked_at', 'expense_id'])]
class EventShoppingItem extends Model
{
    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class, 'added_by_discord_user_id');
    }

    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class, 'checked_by_discord_user_id');
    }

    /**
     * @return BelongsTo<EventExpense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(EventExpense::class, 'expense_id');
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
            'checked_at' => 'datetime',
        ];
    }
}
