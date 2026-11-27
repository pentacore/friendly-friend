<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Currencies an event's costs can be in. All have two decimals, so amounts
 * are stored as minor units (öre, cents, pence) the same way.
 */
enum Currency: string
{
    case Sek = 'SEK';
    case Nok = 'NOK';
    case Dkk = 'DKK';
    case Eur = 'EUR';
    case Gbp = 'GBP';
    case Usd = 'USD';
}
