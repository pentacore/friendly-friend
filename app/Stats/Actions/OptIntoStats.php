<?php

declare(strict_types=1);

namespace App\Stats\Actions;

use App\Models\DiscordUser;
use App\Stats\OptedOutMembers;

/**
 * Turns stats back on for a member. Only activity from now on is counted;
 * what was purged stays gone.
 */
final readonly class OptIntoStats
{
    public function __construct(private OptedOutMembers $optedOut) {}

    public function handle(DiscordUser $member): void
    {
        if ($member->tracking_opted_out_at !== null) {
            $member->forceFill(['tracking_opted_out_at' => null])->save();
            $this->optedOut->refresh();
        }
    }
}
