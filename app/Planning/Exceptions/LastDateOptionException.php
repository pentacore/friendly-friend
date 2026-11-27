<?php

declare(strict_types=1);

namespace App\Planning\Exceptions;

use RuntimeException;

/**
 * A date vote needs at least one proposed date; choose it instead of removing it.
 */
final class LastDateOptionException extends RuntimeException {}
