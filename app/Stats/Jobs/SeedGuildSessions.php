<?php

declare(strict_types=1);

namespace App\Stats\Jobs;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\StatActivitySession;
use App\Models\StatVoiceSession;
use App\Stats\Actions\RecordActivities;
use App\Stats\Actions\RecordVoiceState;
use App\Stats\Tracking;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * After a fresh gateway session (GUILD_CREATE), events missed while the bot
 * was away are gone. Sessions are brought in line with the server's current
 * voice states and games: still-matching ones are kept, others are closed
 * now, and missing ones are opened.
 */
#[Tries(3)]
final class SeedGuildSessions implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $voice  member Discord ID => voice channel ID
     * @param  array<string, array<string, int>>  $playing  member Discord ID => (activity name => type)
     */
    public function __construct(
        public readonly string $guildDiscordId,
        public readonly array $voice,
        public readonly array $playing,
        public readonly string $at,
    ) {}

    public function handle(Tracking $tracking, RecordVoiceState $recordVoice, RecordActivities $recordActivities): void
    {
        $guild = $tracking->guild($this->guildDiscordId);

        if (! $guild instanceof Guild) {
            return;
        }

        $at = CarbonImmutable::parse($this->at);

        $openVoice = $this->discordIdsOf(StatVoiceSession::query()->where('guild_id', $guild->id)->whereNull('ended_at')->select('discord_user_id'));
        $openGames = $this->discordIdsOf(StatActivitySession::query()->where('guild_id', $guild->id)->whereNull('ended_at')->select('discord_user_id'));

        $voiceIds = array_map(strval(...), array_keys($this->voice));
        $playingIds = array_map(strval(...), array_keys($this->playing));
        $members = $tracking->members(array_values(array_unique([...$voiceIds, ...$playingIds, ...$openVoice, ...$openGames])));

        foreach (array_unique([...$voiceIds, ...$openVoice]) as $discordId) {
            $member = $members->get($discordId);

            if ($member !== null) {
                $recordVoice->handle($guild, $member, $this->voice[$discordId] ?? null, $at);
            }
        }

        foreach (array_unique([...$playingIds, ...$openGames]) as $discordId) {
            $member = $members->get($discordId);

            if ($member !== null) {
                $recordActivities->handle($guild, $member, $this->playing[$discordId] ?? [], $at);
            }
        }
    }

    /**
     * Discord IDs of the members in a `discord_user_id` subquery.
     *
     * @param  Builder<covariant Model>  $discordUserIds
     * @return list<string>
     */
    private function discordIdsOf(Builder $discordUserIds): array
    {
        return array_values(DiscordUser::query()
            ->whereIn('id', $discordUserIds)
            ->get(['discord_id'])
            ->map(fn (DiscordUser $user): string => $user->discord_id)
            ->all());
    }
}
