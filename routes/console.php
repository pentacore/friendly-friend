<?php

declare(strict_types=1);

use App\Access\Jobs\SyncAllGuilds;
use App\Access\Jobs\SyncGuildMembers;
use App\Discord\Jobs\RegisterGuildCommands;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Quotes\Imports\StartChannelImport;
use App\Quotes\Jobs\PruneExpiredDrafts;
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

Schedule::job(new PruneExpiredDrafts)->everyMinute()->withoutOverlapping();

Artisan::command('discord:register-commands', function (): void {
    $guilds = Guild::query()->get();
    $guilds->each(fn (Guild $guild) => dispatch_sync(new RegisterGuildCommands($guild->discord_id)));
    $this->info("Registered commands for {$guilds->count()} server(s) (removed from disabled ones).");
})->purpose('Register the /quote and "Save quote" commands in every server (bot token)');

Artisan::command('quotes:import {guild : Discord server ID} {channel : Discord channel ID} {--as= : Discord user ID of the admin requesting it}', function (StartChannelImport $start): int {
    $guild = Guild::query()->enabled()->where('discord_id', $this->argument('guild'))->first();
    $requesterId = $this->option('as');
    $requester = is_string($requesterId) ? DiscordUser::query()->where('discord_id', $requesterId)->first() : null;
    $channelId = $this->argument('channel');

    if ($guild === null || $requester === null) {
        $this->error('Unknown or disabled server, or --as is not a known Discord user ID.');

        return 1;
    }

    if (! is_string($channelId)) {
        return 1;
    }

    $import = $start->handle($guild, $requester, $channelId);
    $this->info("Import #{$import->id} queued. Found quotes wait for review.");

    return 0;
})->purpose('Scan a channel for old quotes (pending review)');
