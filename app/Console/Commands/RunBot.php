<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Discord\DiscordClient;
use App\Discord\Gateway\BotHeartbeat;
use App\Discord\Gateway\GatewayEventRouter;
use App\Discord\Gateway\GatewayIntents;
use Discord\Discord;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The long-running Discord gateway process. It only forwards gateway
 * dispatches to GatewayEventRouter (which queues jobs) and records liveness.
 *
 * Liveness is real traffic from Discord (heartbeat ACKs every ~41 s, plus
 * dispatches), not a timer. If Discord goes quiet for too long, e.g. after
 * a fatal close such as 4004 where DiscordPHP stops reconnecting, the
 * watchdog exits non-zero so Docker restarts the process.
 */
#[Signature('bot:run')]
#[Description('Run the Discord bot (gateway connection)')]
final class RunBot extends Command
{
    private const int OP_DISPATCH = 0;

    private const int OP_HEARTBEAT_ACK = 11;

    /** Give up when Discord has been silent this long (incl. startup). */
    private const int WATCHDOG_TIMEOUT_SECONDS = 180;

    private int $exitCode = self::SUCCESS;

    private float $lastTrafficAt = 0.0;

    private float $lastBeatWrittenAt = 0.0;

    private bool $connected = false;

    public function handle(GatewayEventRouter $router, BotHeartbeat $heartbeat, DiscordClient $client): int
    {
        $token = config()->string('discord.bot_token');

        if ($token === '') {
            $this->error('Set DISCORD_BOT_TOKEN in .env before starting the bot (see docs/discord-app.md).');

            return self::FAILURE;
        }

        // Checked over REST first: DiscordPHP would only log a fatal 4004
        // close (plus an unhandled promise rejection) and keep running.
        if (! $client->tokenIsValid()) {
            $this->error('Discord rejected DISCORD_BOT_TOKEN (401). Reset the token in the developer portal and update .env.');
            Log::error('Discord rejected DISCORD_BOT_TOKEN; not starting the bot.');

            return self::FAILURE;
        }

        $discord = new Discord([
            'token' => $token,
            'intents' => GatewayIntents::value(),
            'loadAllMembers' => false,
            'storeMessages' => false,
            'retrieveBans' => false,
            // We only read voice states; joining voice would need libdave.
            'disableVoiceClient' => true,
            'logger' => Log::channel(),
        ]);

        $this->lastTrafficAt = microtime(true);

        $discord->on('init', function () use ($heartbeat): void {
            $this->connected = true;
            Log::info('Discord bot connected.');
            $this->lastBeatWrittenAt = microtime(true);
            $heartbeat->beat();
        });

        $discord->on('raw', function (object $message) use ($router, $heartbeat): void {
            $this->recordTraffic($heartbeat, $message);
            $this->dispatchToRouter($router, $message);
        });

        $discord->getLoop()->addPeriodicTimer(BotHeartbeat::INTERVAL_SECONDS, function () use ($discord): void {
            $silentFor = microtime(true) - $this->lastTrafficAt;

            if ($silentFor > self::WATCHDOG_TIMEOUT_SECONDS) {
                Log::error($this->connected
                    ? 'Discord gateway silent for too long; exiting so the process is restarted.'
                    : 'Discord bot never finished connecting. Check that the privileged intents (Server Members, Presence, Message Content) are on in the developer portal. Exiting so the process is restarted.', ['seconds' => (int) $silentFor]);
                $this->exitCode = self::FAILURE;
                $discord->close();
            }
        });

        foreach ([SIGINT, SIGTERM] as $signal) {
            $discord->getLoop()->addSignal($signal, function () use ($discord): void {
                Log::info('Discord bot shutting down.');
                $discord->close();
            });
        }

        $discord->run();

        return $this->exitCode;
    }

    /**
     * Heartbeat ACKs and dispatches prove the connection is alive. The shared
     * heartbeat is written at most every INTERVAL_SECONDS, so busy servers
     * (presence updates) don't hammer Redis.
     */
    private function recordTraffic(BotHeartbeat $heartbeat, object $message): void
    {
        $op = $message->op ?? null;

        if ($op !== self::OP_DISPATCH && $op !== self::OP_HEARTBEAT_ACK) {
            return;
        }

        $now = microtime(true);
        $this->lastTrafficAt = $now;

        if ($now - $this->lastBeatWrittenAt >= BotHeartbeat::INTERVAL_SECONDS) {
            $this->lastBeatWrittenAt = $now;
            $heartbeat->beat();
        }
    }

    /**
     * Never let one bad event kill the gateway loop.
     */
    private function dispatchToRouter(GatewayEventRouter $router, object $message): void
    {
        $op = $message->op ?? null;
        $type = $message->t ?? null;

        if ($op !== self::OP_DISPATCH || ! is_string($type)) {
            return;
        }

        try {
            $payload = json_decode(json_encode($message->d ?? null, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
            $router->route($type, is_array($payload) ? $payload : []);
        } catch (Throwable $e) {
            Log::error("Failed to handle {$type} gateway event.", ['exception' => $e]);
        }
    }
}
