<?php

declare(strict_types=1);

namespace App\Discord\Jobs;

use App\Discord\DiscordClient;
use App\Discord\Interactions\BotCommands;
use App\Models\Guild;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Log;

/**
 * Puts the bot's commands in an enabled guild, or removes them from a
 * disabled one. Idempotent (bulk overwrite).
 */
#[Tries(3)]
#[UniqueFor(60)]
final class RegisterGuildCommands implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $guildDiscordId) {}

    public function uniqueId(): string
    {
        return $this->guildDiscordId;
    }

    public function handle(DiscordClient $discord): void
    {
        $guild = Guild::query()->where('discord_id', $this->guildDiscordId)->first();

        if ($guild === null || ! $discord->configured() || config()->string('services.discord.client_id') === '') {
            Log::warning('Skipping command registration: unknown guild, or DISCORD_BOT_TOKEN / DISCORD_CLIENT_ID not set.');

            return;
        }

        $discord->registerGuildCommands($guild->discord_id, $guild->enabled && $guild->bot_present ? BotCommands::definitions() : []);
    }
}
