<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StatReactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Reactions one member added with one emoji on one day, by whose message they reacted to (target null when unknown or opted out).
 *
 * @property int $id
 * @property int $guild_id
 * @property int $reactor_discord_user_id
 * @property int|null $target_discord_user_id
 * @property string $emoji
 * @property Carbon $day
 * @property int $count
 */
#[Table('stat_reactions', timestamps: false)]
#[Fillable(['guild_id', 'reactor_discord_user_id', 'target_discord_user_id', 'emoji', 'day', 'count'])]
class StatReaction extends Model
{
    /** @use HasFactory<StatReactionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'date',
            'count' => 'integer',
        ];
    }
}
