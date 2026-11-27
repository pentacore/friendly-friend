<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's answer to an event question ("yes", an option, or text).
 *
 * @property int $id
 * @property int $event_question_id
 * @property int $discord_user_id
 * @property string $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DiscordUser $discordUser
 */
#[Fillable(['event_question_id', 'discord_user_id', 'value'])]
class EventQuestionAnswer extends Model
{
    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function discordUser(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class);
    }
}
