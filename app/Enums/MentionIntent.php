<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What an @mention of the bot (not a reply) asks for, as decided by Jev.
 * Without Jev, or when it's unsure, a mention is always a capture.
 */
enum MentionIntent: string
{
    case Capture = 'capture';
    case RandomQuote = 'random_quote';
    case Search = 'search';
    case Help = 'help';
}
