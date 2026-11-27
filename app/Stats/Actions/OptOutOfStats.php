<?php

declare(strict_types=1);

namespace App\Stats\Actions;

use App\Models\DiscordUser;
use App\Stats\Jobs\PurgeMemberStats;
use App\Stats\OptedOutMembers;

/**
 * Stops stats for a member and deletes everything already stored about
 * them. Quotes are not affected. Used by /settings/privacy and /privacy.
 */
final readonly class OptOutOfStats
{
    public function __construct(private OptedOutMembers $optedOut) {}

    public function handle(DiscordUser $member): void
    {
        if ($member->tracking_opted_out_at === null) {
            $member->forceFill(['tracking_opted_out_at' => now()])->save();
            $this->optedOut->refresh();
        }

        dispatch(new PurgeMemberStats($member->id));
    }
}
