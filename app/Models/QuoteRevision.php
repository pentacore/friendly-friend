<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Full snapshot of a quote after a save or edit.
 *
 * @property int $id
 * @property int $quote_id
 * @property int $editor_discord_user_id
 * @property array{lines: list<array{speaker: string, person_id: int|null, text: string}>, context: string|null, said_at: string|null} $snapshot
 * @property Carbon|null $created_at
 */
#[Fillable(['quote_id', 'editor_discord_user_id', 'snapshot'])]
class QuoteRevision extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
