<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How an expense is divided.
 */
enum SplitMode: string
{
    /** The same for everyone chosen; leftover öre go to the first ones. */
    case Equal = 'equal';
    /** Everyone's amount typed in; they must add up to the total. */
    case Exact = 'exact';
}
