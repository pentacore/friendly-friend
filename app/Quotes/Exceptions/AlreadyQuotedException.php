<?php

declare(strict_types=1);

namespace App\Quotes\Exceptions;

use App\Models\Quote;
use RuntimeException;

final class AlreadyQuotedException extends RuntimeException
{
    public function __construct(public readonly Quote $existing)
    {
        parent::__construct('That message has already been quoted.');
    }
}
