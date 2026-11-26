<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Discord\Gateway\BotHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bot:status')]
#[Description('Show whether the Discord bot is online (heartbeat)')]
final class BotStatus extends Command
{
    public function handle(BotHeartbeat $heartbeat): int
    {
        $last = $heartbeat->lastBeatAt();

        if ($heartbeat->isOnline()) {
            $this->info("Bot is online (last heartbeat {$last?->diffForHumans()}).");

            return self::SUCCESS;
        }

        $this->error($last instanceof CarbonImmutable
            ? "Bot is offline (last heartbeat {$last->diffForHumans()})."
            : 'Bot is offline (no heartbeat yet).');

        return self::FAILURE;
    }
}
