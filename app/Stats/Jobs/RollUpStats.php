<?php

declare(strict_types=1);

namespace App\Stats\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds one day's rollups from the sessions: voice time, game time and
 * co-presence (two members in the same voice channel at once). The day is
 * in the stats timezone; sessions are clipped to it and open sessions
 * count until now. Replaces the whole day, so it is safe to rerun.
 */
#[Tries(3)]
#[UniqueFor(600)]
final class RollUpStats implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * @param  string|null  $day  Y-m-d, or null for $daysAgo days before today
     */
    public function __construct(public readonly ?string $day = null, public readonly int $daysAgo = 0) {}

    public function uniqueId(): string
    {
        return $this->day ?? "-{$this->daysAgo}";
    }

    public function handle(): void
    {
        $timezone = config()->string('stats.timezone');
        $start = $this->day !== null
            ? CarbonImmutable::parse($this->day, $timezone)->startOfDay()
            : Date::now($timezone)->toImmutable()->startOfDay()->subDays($this->daysAgo);
        $day = $start->toDateString();
        $bounds = [$start->utc()->toIso8601String(), $start->addDay()->utc()->toIso8601String(), Date::now()->toIso8601String()];

        DB::transaction(function () use ($day, $bounds): void {
            DB::table('stat_daily_voice')->where('day', $day)->delete();
            DB::table('stat_daily_activity')->where('day', $day)->delete();
            DB::table('stat_daily_voice_pairs')->where('day', $day)->delete();

            DB::statement(<<<SQL
                insert into stat_daily_voice (guild_id, discord_user_id, day, seconds)
                select guild_id, discord_user_id, ?::date, sum(extract(epoch from until - since))::int
                from ({$this->clipped('stat_voice_sessions', 'channel_id')}) s
                group by guild_id, discord_user_id
                having sum(extract(epoch from until - since)) >= 1
                SQL, [$day, ...$bounds]);

            DB::statement(<<<SQL
                insert into stat_daily_activity (guild_id, discord_user_id, activity_name, day, seconds)
                select guild_id, discord_user_id, activity_name, ?::date, sum(extract(epoch from until - since))::int
                from ({$this->clipped('stat_activity_sessions', 'activity_name')}) s
                group by guild_id, discord_user_id, activity_name
                having sum(extract(epoch from until - since)) >= 1
                SQL, [$day, ...$bounds]);

            DB::statement(<<<SQL
                with s as ({$this->clipped('stat_voice_sessions', 'channel_id')})
                insert into stat_daily_voice_pairs (guild_id, first_discord_user_id, second_discord_user_id, day, seconds)
                select a.guild_id, a.discord_user_id, b.discord_user_id, ?::date,
                    sum(extract(epoch from least(a.until, b.until) - greatest(a.since, b.since)))::int
                from s a
                join s b on b.guild_id = a.guild_id and b.channel_id = a.channel_id and b.discord_user_id > a.discord_user_id
                where least(a.until, b.until) > greatest(a.since, b.since)
                group by a.guild_id, a.discord_user_id, b.discord_user_id
                having sum(extract(epoch from least(a.until, b.until) - greatest(a.since, b.since))) >= 1
                SQL, [...$bounds, $day]);
        });
    }

    /**
     * Sessions overlapping the day, clipped to it (`since`, `until`).
     * Bindings: day start, day end, now.
     */
    private function clipped(string $table, string $key): string
    {
        return <<<SQL
            select guild_id, discord_user_id, {$key},
                greatest(started_at, bounds.day_start) as since,
                least(coalesce(ended_at, bounds.now_at), bounds.day_end) as until
            from {$table}
            cross join (select ?::timestamptz as day_start, ?::timestamptz as day_end, ?::timestamptz as now_at) bounds
            where started_at < bounds.day_end
              and coalesce(ended_at, bounds.now_at) > bounds.day_start
            SQL;
    }
}
