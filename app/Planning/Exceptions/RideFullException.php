<?php

declare(strict_types=1);

namespace App\Planning\Exceptions;

use RuntimeException;

/**
 * Every seat of the ride is taken (or you're the driver).
 */
final class RideFullException extends RuntimeException {}
