<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Rollup: seconds two members shared a voice channel on one day (first id < second id; written by RollUpStats).
 *
 * @property int $id
 * @property int $guild_id
 * @property int $first_discord_user_id
 * @property int $second_discord_user_id
 * @property Carbon $day
 * @property int $seconds
 */
#[Table('stat_daily_voice_pairs', timestamps: false)]
#[Fillable(['guild_id', 'first_discord_user_id', 'second_discord_user_id', 'day', 'seconds'])]
class StatDailyVoicePair extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'date',
            'seconds' => 'integer',
        ];
    }
}
