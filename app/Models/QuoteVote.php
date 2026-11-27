<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One user's 👍 (+1) or 👎 (-1) on a quote.
 *
 * @property int $id
 * @property int $quote_id
 * @property int $user_id
 * @property int $value
 */
#[Fillable(['quote_id', 'user_id', 'value'])]
class QuoteVote extends Model
{
    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
