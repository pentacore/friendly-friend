<?php

declare(strict_types=1);

namespace App\Quotes\Exceptions;

use App\Models\Quote;
use RuntimeException;

/**
 * A Discord message that is already a saved quote was quoted again.
 */
final class AlreadyQuotedException extends RuntimeException
{
    public function __construct(public readonly Quote $existing)
    {
        parent::__construct('That message has already been quoted.');
    }
}
