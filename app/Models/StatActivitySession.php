<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\StatActivitySessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Time one member spent playing one game, as seen in one server. `ended_at` is null while they still play.
 *
 * @property int $id
 * @property int $guild_id
 * @property int $discord_user_id
 * @property string $activity_name
 * @property int $activity_type
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ended_at
 */
#[Table('stat_activity_sessions', timestamps: false)]
#[Fillable(['guild_id', 'discord_user_id', 'activity_name', 'activity_type', 'started_at', 'ended_at'])]
class StatActivitySession extends Model
{
    /** @use HasFactory<StatActivitySessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'activity_type' => 'integer',
        ];
    }
}
