<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DateVote;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's yes / if need be / no for a proposed date.
 *
 * @property int $id
 * @property int $event_date_option_id
 * @property int $discord_user_id
 * @property DateVote $answer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DiscordUser $discordUser
 */
#[Fillable(['event_date_option_id', 'discord_user_id', 'answer'])]
class EventDateVote extends Model
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
        return ['answer' => DateVote::class];
    }
}
