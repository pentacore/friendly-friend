<?php

declare(strict_types=1);

namespace App\Access\Jobs;

use App\Access\Actions\UpsertDiscordMember;
use App\Discord\Data\GuildMemberData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * A member joined or changed (gateway GUILD_MEMBER_ADD / _UPDATE).
 */
#[Tries(3)]
final class UpsertGuildMember implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly GuildMemberData $member) {}

    public function handle(UpsertDiscordMember $upsert): void
    {
        $upsert->handle($this->member);
    }
}
