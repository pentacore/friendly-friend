<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\StatVoiceSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Time one member spent in one voice channel. `ended_at` is null while they are still there.
 *
 * @property int $id
 * @property int $guild_id
 * @property int $discord_user_id
 * @property string $channel_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ended_at
 */
#[Table('stat_voice_sessions', timestamps: false)]
#[Fillable(['guild_id', 'discord_user_id', 'channel_id', 'started_at', 'ended_at'])]
class StatVoiceSession extends Model
{
    /** @use HasFactory<StatVoiceSessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
