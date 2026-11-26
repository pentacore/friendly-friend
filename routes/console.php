<?php

declare(strict_types=1);

use App\Access\Jobs\SyncGuildMembers;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('discord:sync-members', function (): void {
    dispatch_sync(new SyncGuildMembers);
    $this->info('Guild members synced.');
})->purpose('Refresh all Discord guild members now (bot token)');

Schedule::job(new SyncGuildMembers)->cron('0 */3 * * *')->withoutOverlapping();
