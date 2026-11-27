<?php

declare(strict_types=1);

namespace App\Enums;

enum SaidAtPrecision: string
{
    /** From a Discord message timestamp. */
    case Exact = 'exact';
    /** "yesterday", "on the 3rd". */
    case Day = 'day';
    /** "last summer", "a while ago". */
    case Approximate = 'approximate';
}
