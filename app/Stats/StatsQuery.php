<?php

declare(strict_types=1);

namespace App\Stats;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\Person;
use App\Models\StatDailyActivity;
use App\Models\StatDailyVoice;
use App\Models\StatDailyVoicePair;
use App\Models\StatMessage;
use App\Models\StatReaction;
use App\Models\StatVoiceSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Read model behind /stats and the stats on people pages: totals,
 * leaderboards and the heatmap for some servers over the last N local days.
 * Opted-out members have no rows left, so they never show up.
 *
 * @phpstan-type Member array{id: int, name: string, avatar_url: string, person_slug: string|null}
 */
final readonly class StatsQuery
{
    private string $timezone;

    private CarbonImmutable $since;

    /**
     * @param  list<int>  $guildIds
     */
    public function __construct(private array $guildIds, int $days)
    {
        $this->timezone = config()->string('stats.timezone');
        $this->since = Date::now($this->timezone)->toImmutable()->startOfDay()->subDays($days - 1);
    }

    /**
     * @return array{messages: int, voice_seconds: int, reactions: int, members: int}
     */
    public function totals(?int $discordUserId = null): array
    {
        $messages = $this->messages()->when($discordUserId, fn (Builder $q, int $id) => $q->where('discord_user_id', $id));
        $voice = $this->daily(StatDailyVoice::query())->when($discordUserId, fn (Builder $q, int $id) => $q->where('discord_user_id', $id));
        $reactions = $this->reactions()->when($discordUserId, fn (Builder $q, int $id) => $q->where('reactor_discord_user_id', $id));

        return [
            'messages' => (int) $messages->sum('count'),
            'voice_seconds' => (int) $voice->sum('seconds'),
            'reactions' => (int) $reactions->sum('count'),
            'members' => $discordUserId !== null ? 1 : $this->messages()->distinct()->count('discord_user_id'),
        ];
    }

    /**
     * Messages per local weekday (1 = Monday) and hour.
     *
     * @return list<array{weekday: int, hour: int, count: int}>
     */
    public function heatmap(?int $discordUserId = null): array
    {
        $rows = $this->messages()
            ->when($discordUserId, fn (Builder $q, int $id) => $q->where('discord_user_id', $id))
            ->selectRaw('extract(isodow from hour at time zone ?)::int as weekday, extract(hour from hour at time zone ?)::int as local_hour, sum(count)::int as total', [$this->timezone, $this->timezone])
            ->groupByRaw('1, 2')
            ->toBase()
            ->get();

        return array_values($rows->map(fn (object $row): array => [
            'weekday' => $this->int($row, 'weekday'),
            'hour' => $this->int($row, 'local_hour'),
            'count' => $this->int($row, 'total'),
        ])->all());
    }

    /**
     * Messages sent.
     *
     * @return list<array{member: Member, total: int}>
     */
    public function topMessagers(int $limit = 10): array
    {
        return $this->ranked($this->messages(), 'discord_user_id', 'sum(count)', $limit);
    }

    /**
     * Seconds in voice.
     *
     * @return list<array{member: Member, total: int}>
     */
    public function topVoice(int $limit = 10): array
    {
        return $this->ranked($this->daily(StatDailyVoice::query()), 'discord_user_id', 'sum(seconds)', $limit);
    }

    /**
     * Reactions added.
     *
     * @return list<array{member: Member, total: int}>
     */
    public function topReactors(int $limit = 10): array
    {
        return $this->ranked($this->reactions(), 'reactor_discord_user_id', 'sum(count)', $limit);
    }

    /**
     * Who spent the most time in the same voice channel.
     *
     * @return list<array{first: Member, second: Member, total: int}>
     */
    public function voicePairs(?int $discordUserId = null, int $limit = 10): array
    {
        $rows = $this->daily(StatDailyVoicePair::query())
            ->when($discordUserId, fn (Builder $q, int $id) => $q->where(fn (Builder $q) => $q->where('first_discord_user_id', $id)->orWhere('second_discord_user_id', $id)))
            ->selectRaw('first_discord_user_id as first_id, second_discord_user_id as second_id, sum(seconds)::int as total')
            ->groupBy('first_discord_user_id', 'second_discord_user_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->toBase()
            ->get();

        $members = $this->members($rows->flatMap(fn (object $row): array => [$this->int($row, 'first_id'), $this->int($row, 'second_id')])->all());

        return array_values($rows->map(fn (object $row): array => [
            'first' => $members[$this->int($row, 'first_id')],
            'second' => $members[$this->int($row, 'second_id')],
            'total' => $this->int($row, 'total'),
        ])->all());
    }

    /**
     * Who reacts most to whose messages (not their own).
     *
     * @return list<array{first: Member, second: Member, total: int}>
     */
    public function reactionPairs(int $limit = 10): array
    {
        $rows = $this->reactions()
            ->whereNotNull('target_discord_user_id')
            ->whereColumn('target_discord_user_id', '!=', 'reactor_discord_user_id')
            ->selectRaw('reactor_discord_user_id as first_id, target_discord_user_id as second_id, sum(count)::int as total')
            ->groupBy('reactor_discord_user_id', 'target_discord_user_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->toBase()
            ->get();

        $members = $this->members($rows->flatMap(fn (object $row): array => [$this->int($row, 'first_id'), $this->int($row, 'second_id')])->all());

        return array_values($rows->map(fn (object $row): array => [
            'first' => $members[$this->int($row, 'first_id')],
            'second' => $members[$this->int($row, 'second_id')],
            'total' => $this->int($row, 'total'),
        ])->all());
    }

    /**
     * @return list<array{emoji: string, name: string, image_url: string|null, total: int}>
     */
    public function topEmoji(?int $discordUserId = null, int $limit = 10): array
    {
        $rows = $this->reactions()
            ->when($discordUserId, fn (Builder $q, int $id) => $q->where('reactor_discord_user_id', $id))
            ->selectRaw('emoji, sum(count)::int as total')
            ->groupBy('emoji')
            ->orderByDesc('total')
            ->limit($limit)
            ->toBase()
            ->get();

        return array_values($rows->map(function (object $row): array {
            $emoji = $this->string($row, 'emoji');
            $isCustom = str_contains($emoji, ':');

            return [
                'emoji' => $emoji,
                'name' => Str::after($emoji, ':'),
                'image_url' => $isCustom ? config()->string('discord.cdn_base').'/emojis/'.Str::before($emoji, ':').'.webp?size=64' : null,
                'total' => $this->int($row, 'total'),
            ];
        })->all());
    }

    /**
     * @return list<array{name: string, total: int, players: int}>
     */
    public function games(?int $discordUserId = null, int $limit = 10): array
    {
        $rows = $this->daily(StatDailyActivity::query())
            ->when($discordUserId, fn (Builder $q, int $id) => $q->where('discord_user_id', $id))
            ->selectRaw('activity_name, sum(seconds)::int as total, count(distinct discord_user_id)::int as players')
            ->groupBy('activity_name')
            ->orderByDesc('total')
            ->limit($limit)
            ->toBase()
            ->get();

        return array_values($rows->map(fn (object $row): array => [
            'name' => $this->string($row, 'activity_name'),
            'total' => $this->int($row, 'total'),
            'players' => $this->int($row, 'players'),
        ])->all());
    }

    /**
     * Messages per channel, named from the servers' cached channel lists
     * (channels the bot can no longer see show as "a hidden channel").
     *
     * @return list<array{channel_id: string, name: string, guild: string, total: int}>
     */
    public function topChannels(int $limit = 10): array
    {
        $rows = $this->messages()
            ->selectRaw('guild_id, channel_id, sum(count)::int as total')
            ->groupBy('guild_id', 'channel_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->toBase()
            ->get();

        $guilds = Guild::query()->whereIn('id', $rows->map(fn (object $row): int => $this->int($row, 'guild_id'))->all())->get()->keyBy('id');

        return array_values($rows->map(function (object $row) use ($guilds): array {
            $guild = $guilds->get($this->int($row, 'guild_id'));
            $channelId = $this->string($row, 'channel_id');
            $channel = $guild instanceof Guild ? collect($guild->channels)->firstWhere('id', $channelId) : null;

            return [
                'channel_id' => $channelId,
                'name' => is_array($channel) ? '#'.$channel['name'] : 'a hidden channel',
                'guild' => $guild instanceof Guild ? $guild->name : '',
                'total' => $this->int($row, 'total'),
            ];
        })->all());
    }

    /**
     * The period's records: the longest single voice session (open ones count
     * until now), the most messages one member sent in a local day, and the
     * longest run of consecutive local days with messages (two days or more).
     *
     * @return array{
     *     longest_voice: array{member: Member, seconds: int, started_at: string}|null,
     *     busiest_day: array{member: Member, total: int, day: string}|null,
     *     longest_streak: array{member: Member, days: int, ended_on: string}|null,
     * }
     */
    public function records(): array
    {
        $now = Date::now()->toImmutable();

        $voice = StatVoiceSession::query()
            ->whereIn('guild_id', $this->guildIds)
            ->where('started_at', '>=', $this->since->utc())
            ->selectRaw('discord_user_id as member_id, started_at, extract(epoch from (coalesce(ended_at, ?::timestamptz) - started_at))::int as total', [$now->toIso8601String()])
            ->orderByDesc('total')
            ->limit(1)
            ->toBase()
            ->first();

        $busiest = $this->messages()
            ->selectRaw('discord_user_id as member_id, (hour at time zone ?)::date::text as local_day, sum(count)::int as total', [$this->timezone])
            ->groupByRaw('1, 2')
            ->orderByDesc('total')
            ->orderByDesc('local_day')
            ->limit(1)
            ->toBase()
            ->first();

        // Gaps and islands: consecutive days share the same day minus row number.
        $activeDays = $this->messages()->selectRaw('distinct discord_user_id, (hour at time zone ?)::date as local_day', [$this->timezone]);
        $islands = DB::query()->fromSub($activeDays, 'active_days')
            ->selectRaw('discord_user_id, local_day, local_day - (row_number() over (partition by discord_user_id order by local_day))::int as island');
        $streak = DB::query()->fromSub($islands, 'islands')
            ->selectRaw('discord_user_id as member_id, count(*)::int as total, max(local_day)::text as ended_on')
            ->groupBy('discord_user_id', 'island')
            ->havingRaw('count(*) >= 2')
            ->orderByDesc('total')
            ->orderByDesc('ended_on')
            ->limit(1)
            ->first();

        $members = $this->members(array_map(
            fn (object $row): int => $this->int($row, 'member_id'),
            array_filter([$voice, $busiest, $streak], is_object(...)),
        ));

        return [
            'longest_voice' => is_object($voice) ? [
                'member' => $members[$this->int($voice, 'member_id')],
                'seconds' => $this->int($voice, 'total'),
                'started_at' => CarbonImmutable::parse($this->string($voice, 'started_at'))->toIso8601String(),
            ] : null,
            'busiest_day' => is_object($busiest) ? [
                'member' => $members[$this->int($busiest, 'member_id')],
                'total' => $this->int($busiest, 'total'),
                'day' => $this->string($busiest, 'local_day'),
            ] : null,
            'longest_streak' => is_object($streak) ? [
                'member' => $members[$this->int($streak, 'member_id')],
                'days' => $this->int($streak, 'total'),
                'ended_on' => $this->string($streak, 'ended_on'),
            ] : null,
        ];
    }

    /**
     * @return Builder<StatMessage>
     */
    private function messages(): Builder
    {
        return StatMessage::query()->whereIn('guild_id', $this->guildIds)->where('hour', '>=', $this->since->utc());
    }

    /**
     * @return Builder<StatReaction>
     */
    private function reactions(): Builder
    {
        return StatReaction::query()->whereIn('guild_id', $this->guildIds)->where('day', '>=', $this->since->toDateString());
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function daily(Builder $query): Builder
    {
        return $query->whereIn('guild_id', $this->guildIds)->where('day', '>=', $this->since->toDateString());
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  literal-string  $memberColumn
     * @param  literal-string  $aggregate
     * @return list<array{member: Member, total: int}>
     */
    private function ranked(Builder $query, string $memberColumn, string $aggregate, int $limit): array
    {
        $rows = $query
            ->selectRaw("{$memberColumn} as member_id, {$aggregate}::int as total")
            ->groupBy($memberColumn)
            ->orderByDesc('total')
            ->limit($limit)
            ->toBase()
            ->get();

        $members = $this->members($rows->map(fn (object $row): int => $this->int($row, 'member_id'))->all());

        return array_values($rows->map(fn (object $row): array => [
            'member' => $members[$this->int($row, 'member_id')],
            'total' => $this->int($row, 'total'),
        ])->all());
    }

    /**
     * @param  array<int>  $ids
     * @return array<int, Member>
     */
    private function members(array $ids): array
    {
        $slugs = Person::query()->whereIn('discord_user_id', $ids)->get(['discord_user_id', 'slug'])
            ->mapWithKeys(fn (Person $person): array => [(int) $person->discord_user_id => $person->slug]);

        return DiscordUser::query()->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (DiscordUser $user): array => [$user->id => [
                'id' => $user->id,
                'name' => $user->display_name,
                'avatar_url' => $user->avatarUrl(),
                'person_slug' => $slugs->get($user->id),
            ]])
            ->all();
    }

    private function int(object $row, string $key): int
    {
        $value = $row->{$key} ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function string(object $row, string $key): string
    {
        $value = $row->{$key} ?? '';

        return is_string($value) ? $value : '';
    }
}
