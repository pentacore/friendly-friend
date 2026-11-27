<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of answer an event question takes.
 */
enum QuestionKind: string
{
    /** "Will you eat crayfish?": yes or no. */
    case YesNo = 'yes_no';
    /** "Which sauna slot?": one of the organiser's options. */
    case Choice = 'choice';
    /** "Anything we should know?": free text. */
    case Text = 'text';
}
