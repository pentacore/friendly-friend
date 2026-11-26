<?php

declare(strict_types=1);

namespace App\Discord\Gateway;

use App\Access\Jobs\MarkMemberLeft;
use App\Access\Jobs\SyncGuildMembers;
use App\Access\Jobs\UpsertGuildMember;
use App\Discord\Data\GuildMemberData;
use Illuminate\Support\Facades\Log;

/**
 * Turns gateway dispatch events (type + decoded `d` payload) into queued
 * jobs. Runs inside the bot process, so it must stay cheap: no database, no
 * HTTP, no business logic. It only checks the guild and queues work.
 */
final readonly class GatewayEventRouter
{
    /**
     * @param  array<mixed>  $payload
     */
    public function route(string $type, array $payload): void
    {
        match ($type) {
            'GUILD_MEMBER_ADD', 'GUILD_MEMBER_UPDATE' => $this->memberChanged($type, $payload),
            'GUILD_MEMBER_REMOVE' => $this->memberLeft($type, $payload),
            'GUILD_CREATE' => $this->guildAvailable($payload),
            default => null,
        };
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function memberChanged(string $type, array $payload): void
    {
        if (! $this->isOurGuild($payload['guild_id'] ?? null)) {
            return;
        }

        $member = GuildMemberData::fromPayload($payload);

        if ($member->discordId === '' || $member->username === '') {
            $this->malformed($type);

            return;
        }

        dispatch(new UpsertGuildMember($member));
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function memberLeft(string $type, array $payload): void
    {
        if (! $this->isOurGuild($payload['guild_id'] ?? null)) {
            return;
        }

        $user = $payload['user'] ?? null;
        $discordId = is_array($user) && is_string($user['id'] ?? null) ? $user['id'] : null;

        if ($discordId === null) {
            $this->malformed($type);

            return;
        }

        dispatch(new MarkMemberLeft($discordId));
    }

    /**
     * Sent on connect and after outages: refresh every member, since member
     * events may have been missed while the bot was away.
     *
     * @param  array<mixed>  $payload
     */
    private function guildAvailable(array $payload): void
    {
        if ($this->isOurGuild($payload['id'] ?? null)) {
            dispatch(new SyncGuildMembers);
        }
    }

    private function isOurGuild(mixed $guildId): bool
    {
        return is_string($guildId) && $guildId === config()->string('discord.guild_id');
    }

    private function malformed(string $type): void
    {
        Log::warning("Dropping malformed {$type} gateway event.");
    }
}
