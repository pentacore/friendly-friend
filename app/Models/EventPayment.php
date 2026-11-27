<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money that changed hands to settle an event's costs (minor units).
 *
 * @property int $id
 * @property int $event_id
 * @property int $from_discord_user_id
 * @property int $to_discord_user_id
 * @property int $recorded_by_discord_user_id
 * @property int $amount
 * @property Carbon $settled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DiscordUser $from
 * @property-read DiscordUser $to
 */
#[Fillable(['event_id', 'from_discord_user_id', 'to_discord_user_id', 'recorded_by_discord_user_id', 'amount', 'settled_at'])]
class EventPayment extends Model
{
    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function from(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class, 'from_discord_user_id');
    }

    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function to(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class, 'to_discord_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'settled_at' => 'datetime',
        ];
    }
}
