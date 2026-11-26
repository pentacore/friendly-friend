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

    public function beat(): void
    {
        Cache::forever(self::KEY, Date::now()->getTimestamp());
    }

    public function lastBeatAt(): ?CarbonImmutable
    {
        $timestamp = Cache::get(self::KEY);

        return is_int($timestamp) ? CarbonImmutable::createFromTimestamp($timestamp) : null;
    }

    public function isOnline(): bool
    {
        $last = $this->lastBeatAt();

        return $last instanceof CarbonImmutable
            && $last->diffInSeconds(Date::now(), absolute: true) < self::STALE_AFTER_SECONDS;
    }
}
