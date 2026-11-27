<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Rollup: seconds one member spent in voice on one day (written by RollUpStats).
 *
 * @property int $id
 * @property int $guild_id
 * @property int $discord_user_id
 * @property Carbon $day
 * @property int $seconds
 */
#[Table('stat_daily_voice', timestamps: false)]
#[Fillable(['guild_id', 'discord_user_id', 'day', 'seconds'])]
class StatDailyVoice extends Model
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
