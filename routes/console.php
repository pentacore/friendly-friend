<?php

declare(strict_types=1);

use App\Access\Jobs\SyncAllGuilds;
use App\Access\Jobs\SyncGuildMembers;
use App\Models\Guild;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('discord:sync-members', function (): void {
    $guilds = Guild::query()->enabled()->get();

    if ($guilds->isEmpty()) {
        $this->warn('No enabled servers. Enable one on /admin/guilds first.');

        return;
    }

    $guilds->each(fn (Guild $guild) => dispatch_sync(new SyncGuildMembers($guild->discord_id)));
    $this->info("Synced members of {$guilds->count()} server(s).");
})->purpose('Refresh all members of every enabled Discord server now (bot token)');

Schedule::job(new SyncAllGuilds)->cron('0 */3 * * *')->withoutOverlapping();
