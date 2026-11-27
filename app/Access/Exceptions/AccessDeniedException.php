<?php

declare(strict_types=1);

namespace App\Access\Exceptions;

use App\Enums\AccessDeniedReason;
use RuntimeException;

/**
 * Thrown by LoginWithDiscord when someone may not use the site; carries the reason shown on the login page.
 */
final class AccessDeniedException extends RuntimeException
{
    public function __construct(public readonly AccessDeniedReason $reason)
    {
        parent::__construct("Access denied: {$reason->value}");
    }
}
