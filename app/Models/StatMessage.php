<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Messages one member sent in one channel during one hour (a counter, never content).
 *
 * @property int $id
 * @property int $guild_id
 * @property int $discord_user_id
 * @property string $channel_id
 * @property Carbon $hour
 * @property int $count
 */
#[Table('stat_messages', timestamps: false)]
#[Fillable(['guild_id', 'discord_user_id', 'channel_id', 'hour', 'count'])]
class StatMessage extends Model
{
    /** @use HasFactory<StatMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hour' => 'datetime',
            'count' => 'integer',
        ];
    }
}
