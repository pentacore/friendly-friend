<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A backfill of old quotes from a channel's history.
 *
 * @property int $id
 * @property int $guild_id
 * @property string $channel_id
 * @property int $requested_by_discord_user_id
 * @property string $status queued|running|done|failed
 * @property string|null $before_message_id resume cursor (oldest message seen)
 * @property int $messages_scanned
 * @property int $quotes_found
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property-read Guild $guild
 */
#[Fillable(['guild_id', 'channel_id', 'requested_by_discord_user_id', 'status', 'before_message_id', 'messages_scanned', 'quotes_found', 'error', 'started_at', 'finished_at'])]
class QuoteImport extends Model
{
    /**
     * @return BelongsTo<Guild, $this>
     */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'import_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
