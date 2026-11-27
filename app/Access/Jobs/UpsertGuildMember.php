<?php

declare(strict_types=1);

namespace App\Access\Jobs;

use App\Access\Actions\RecordGuildMember;
use App\Discord\Data\GuildMemberData;
use App\Models\Guild;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * A member joined or changed (GUILD_MEMBER_ADD / _UPDATE). Ignored for
 * guilds that are unknown or not enabled.
 */
#[Tries(3)]
final class UpsertGuildMember implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $guildDiscordId,
        public readonly GuildMemberData $member,
    ) {}

    public function handle(RecordGuildMember $record): void
    {
        $guild = Guild::query()->enabled()->where('discord_id', $this->guildDiscordId)->first();

        if ($guild !== null) {
            $record->handle($guild, $this->member);
        }
    }
}
