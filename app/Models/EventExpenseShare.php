<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's part of an expense (minor units).
 *
 * @property int $id
 * @property int $event_expense_id
 * @property int $discord_user_id
 * @property int $amount
 * @property-read DiscordUser $discordUser
 */
#[Table('event_expense_shares', timestamps: false)]
#[Fillable(['event_expense_id', 'discord_user_id', 'amount'])]
class EventExpenseShare extends Model
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
        return ['amount' => 'integer'];
    }
}
