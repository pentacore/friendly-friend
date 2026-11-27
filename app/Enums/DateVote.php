<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether someone can make a proposed date.
 */
enum DateVote: string
{
    case Yes = 'yes';
    case IfNeedBe = 'if_need_be';
    case No = 'no';
}
