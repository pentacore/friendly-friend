<?php

declare(strict_types=1);

namespace App\Access\Jobs;

use App\Models\Guild;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queues a member sync for every enabled guild (scheduled every 3 hours).
 */
final class SyncAllGuilds implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Guild::query()->enabled()->get()
            ->each(fn (Guild $guild): PendingDispatch => dispatch(new SyncGuildMembers($guild->discord_id)));
    }
}
