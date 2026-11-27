<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A this-or-that pick: which of two quotes a user found funnier.
 *
 * @property int $id
 * @property int $user_id
 * @property int $quote_a_id
 * @property int $quote_b_id
 * @property int $winner_quote_id
 */
#[Fillable(['user_id', 'quote_a_id', 'quote_b_id', 'winner_quote_id'])]
class QuoteMatchup extends Model
{
    public const UPDATED_AT = null;
}
