<?php

declare(strict_types=1);

namespace App\Discord\Gateway;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * Liveness of the bot's Discord connection. `bot:run` beats (at most every
 * 30 s) whenever Discord traffic arrives (heartbeat ACKs about every 41 s),
 * so it is online if it beat within the last 90 seconds.
 */
final readonly class BotHeartbeat
{
    public const int INTERVAL_SECONDS = 30;

    private const int STALE_AFTER_SECONDS = 90;

    private const string KEY = 'discord:bot:heartbeat';

    private const string STARTED_KEY = 'discord:bot:started';

    private const int RESTART_WINDOW_MINUTES = 10;

    /**
     * Stored as an ISO-8601 string: Redis returns plain numbers as strings,
     * so a string round-trips the same on every cache store.
     */
    public function beat(): void
    {
        Cache::forever(self::KEY, Date::now()->toIso8601String());
    }

    public function lastBeatAt(): ?CarbonImmutable
    {
        $beat = Cache::get(self::KEY);

        return is_string($beat) ? CarbonImmutable::parse($beat) : null;
    }

    /**
     * Called once when `bot:run` starts, before connecting: remembers when
     * this process started and the previous process's last beat.
     */
    public function markStarted(): void
    {
        Cache::forever(self::STARTED_KEY, [
            'started' => Date::now()->toIso8601String(),
            'previous' => $this->lastBeatAt()?->toIso8601String(),
        ]);
    }

    /**
     * The last sign of life before this process started, for closing stats
     * sessions that ended while the bot was down. Only during the first
     * minutes after a start; later reconnects (outages mid-run) get null.
     */
    public function downSince(): ?CarbonImmutable
    {
        $marker = Cache::get(self::STARTED_KEY);

        if (! is_array($marker) || ! is_string($marker['started'] ?? null) || ! is_string($marker['previous'] ?? null)) {
            return null;
        }

        $started = CarbonImmutable::parse($marker['started']);
        $previous = CarbonImmutable::parse($marker['previous']);

        return $started->diffInMinutes(Date::now(), absolute: true) <= self::RESTART_WINDOW_MINUTES && $previous->lessThan($started)
            ? $previous
            : null;
    }

    public function isOnline(): bool
    {
        $last = $this->lastBeatAt();

        return $last instanceof CarbonImmutable
            && $last->diffInSeconds(Date::now(), absolute: true) < self::STALE_AFTER_SECONDS;
    }
}
