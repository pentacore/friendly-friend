<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuoteSource;
use App\Enums\QuoteStatus;
use App\Enums\SaidAtPrecision;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $guild_id
 * @property QuoteStatus $status
 * @property QuoteSource $source
 * @property string|null $context
 * @property Carbon|null $said_at
 * @property SaidAtPrecision|null $said_at_precision
 * @property string|null $source_message_id
 * @property string|null $source_channel_id
 * @property string|null $source_message_url
 * @property int|null $import_id
 * @property int $submitted_by_discord_user_id
 * @property string $raw_input
 * @property array<string, mixed>|null $ai_output
 * @property int $score
 * @property int $elo
 * @property int $matchup_count
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Guild $guild
 * @property-read DiscordUser $submittedBy
 * @property-read Collection<int, QuoteLine> $lines
 */
#[Fillable(['guild_id', 'status', 'source', 'context', 'said_at', 'said_at_precision', 'source_message_id', 'source_channel_id', 'source_message_url', 'import_id', 'submitted_by_discord_user_id', 'raw_input', 'ai_output', 'published_at'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Guild, $this>
     */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class, 'submitted_by_discord_user_id');
    }

    /**
     * @return HasMany<QuoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<QuoteRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(QuoteRevision::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'source' => QuoteSource::class,
            'said_at' => 'datetime',
            'said_at_precision' => SaidAtPrecision::class,
            'ai_output' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
