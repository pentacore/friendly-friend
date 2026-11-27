<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SplitMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Something one person paid for an event, split between some people.
 * Amounts are minor units of the event's currency.
 *
 * @property int $id
 * @property int $event_id
 * @property int $paid_by_discord_user_id
 * @property int $added_by_discord_user_id
 * @property string $description
 * @property int $amount
 * @property SplitMode $split_mode
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DiscordUser $paidBy
 * @property-read Collection<int, EventExpenseShare> $shares
 * @property-read Collection<int, EventShoppingItem> $shoppingItems what it paid for
 */
#[Fillable(['event_id', 'paid_by_discord_user_id', 'added_by_discord_user_id', 'description', 'amount', 'split_mode'])]
class EventExpense extends Model
{
    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class, 'paid_by_discord_user_id');
    }

    /**
     * @return HasMany<EventShoppingItem, $this>
     */
    public function shoppingItems(): HasMany
    {
        return $this->hasMany(EventShoppingItem::class, 'expense_id');
    }

    /**
     * @return HasMany<EventExpenseShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(EventExpenseShare::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'split_mode' => SplitMode::class,
        ];
    }
}
