<?php

declare(strict_types=1);

namespace App\Quotes;

use App\Access\AccessSnapshot;
use App\Enums\QuoteStatus;
use App\Models\Quote;

/**
 * Who may do what with a quote. Shared by QuotePolicy and QuoteResource
 * (which uses the request's AccessSnapshot to avoid per-quote queries).
 */
final class QuoteRules
{
    public static function canView(AccessSnapshot $access, Quote $quote): bool
    {
        return $quote->status === QuoteStatus::Published && $access->canSeeGuild($quote->guild_id);
    }

    /**
     * The submitter (while they can still see the guild) and the guild's admins.
     */
    public static function canUpdate(AccessSnapshot $access, Quote $quote): bool
    {
        if ($access->moderates($quote->guild_id)) {
            return true;
        }

        return $quote->submitted_by_discord_user_id === $access->discordUserId
            && $access->canSeeGuild($quote->guild_id)
            && in_array($quote->status, [QuoteStatus::Published, QuoteStatus::Draft], true);
    }

    public static function canDelete(AccessSnapshot $access, Quote $quote): bool
    {
        return $access->moderates($quote->guild_id);
    }

    /**
     * Approving or rejecting imported quotes.
     */
    public static function canReview(AccessSnapshot $access, Quote $quote): bool
    {
        return $quote->status === QuoteStatus::PendingReview && $access->moderates($quote->guild_id);
    }
}
