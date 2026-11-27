<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a quote: draft → published (imports: pending_review → published), or removed.
 */
enum QuoteStatus: string
{
    /** Being captured; only the submitter sees the private preview. Expires after 15 minutes. */
    case Draft = 'draft';
    /** Found by a channel import; waits for an admin. */
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Removed = 'removed';
}
