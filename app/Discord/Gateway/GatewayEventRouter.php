<?php

declare(strict_types=1);

namespace App\Discord\Gateway;

use App\Access\Jobs\MarkMemberLeft;
use App\Access\Jobs\UpsertGuildMember;
use App\Discord\Data\GuildData;
use App\Discord\Data\GuildMemberData;
use App\Discord\Interactions\InteractionAck;
use App\Discord\Interactions\InteractionAcknowledger;
use App\Discord\Jobs\MarkGuildLeft;
use App\Discord\Jobs\RefreshGuild;
use App\Discord\Jobs\RegisterGuild;
use App\Quotes\Jobs\CaptureFromMention;
use App\Quotes\Jobs\HandleQuoteInteraction;
use Illuminate\Support\Facades\Log;

/**
 * Turns gateway dispatch events (type + decoded `d` payload) into queued
 * jobs. Runs inside the bot process, so it must stay cheap: no database, no
 * HTTP, no business logic. Whether a guild is enabled is the jobs' concern.
 */
final readonly class GatewayEventRouter
{
    /**
     * @param  array<mixed>  $payload
     */
    public function route(string $type, array $payload): void
    {
        match ($type) {
            'GUILD_CREATE' => $this->guildAvailable($type, $payload, syncMembers: true),
            'GUILD_UPDATE' => $this->guildAvailable($type, $payload, syncMembers: false),
            'GUILD_DELETE' => $this->guildRemoved($payload),
            'GUILD_ROLE_CREATE', 'GUILD_ROLE_UPDATE', 'GUILD_ROLE_DELETE' => $this->rolesChanged($type, $payload),
            'GUILD_MEMBER_ADD', 'GUILD_MEMBER_UPDATE' => $this->memberChanged($type, $payload),
            'GUILD_MEMBER_REMOVE' => $this->memberLeft($type, $payload),
            'INTERACTION_CREATE' => $this->interaction($payload),
            'MESSAGE_CREATE' => $this->message($payload),
            default => null,
        };
    }

    /**
     * GUILD_CREATE arrives when the bot joins a guild and for every guild on
     * connect, so it also triggers a member sync to catch missed events.
     *
     * @param  array<mixed>  $payload
     */
    private function guildAvailable(string $type, array $payload, bool $syncMembers): void
    {
        if (($payload['unavailable'] ?? false) === true) {
            return;
        }

        $guild = GuildData::fromPayload($payload);

        if (! $guild->isValid()) {
            $this->malformed($type);

            return;
        }

        dispatch(new RegisterGuild($guild, $syncMembers));
    }

    /**
     * `unavailable: true` means a Discord outage, not that the bot was removed.
     *
     * @param  array<mixed>  $payload
     */
    private function guildRemoved(array $payload): void
    {
        $guildId = $this->string($payload, 'id');

        if ($guildId !== null && ($payload['unavailable'] ?? false) !== true) {
            dispatch(new MarkGuildLeft($guildId));
        }
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function rolesChanged(string $type, array $payload): void
    {
        $guildId = $this->string($payload, 'guild_id');

        if ($guildId === null) {
            $this->malformed($type);

            return;
        }

        dispatch(new RefreshGuild($guildId));
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function memberChanged(string $type, array $payload): void
    {
        $guildId = $this->string($payload, 'guild_id');
        $member = GuildMemberData::fromPayload($payload);

        if ($guildId === null || ! $member->isValid()) {
            $this->malformed($type);

            return;
        }

        dispatch(new UpsertGuildMember($guildId, $member));
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function memberLeft(string $type, array $payload): void
    {
        $guildId = $this->string($payload, 'guild_id');
        $user = $payload['user'] ?? null;
        $userId = is_array($user) ? $this->string($user, 'id') : null;

        if ($guildId === null || $userId === null) {
            $this->malformed($type);

            return;
        }

        dispatch(new MarkMemberLeft($guildId, $userId));
    }

    /**
     * Acknowledge within Discord's 3 seconds (the only Discord call allowed
     * in the bot process), then queue the real work.
     *
     * @param  array<mixed>  $payload
     */
    private function interaction(array $payload): void
    {
        $body = InteractionAck::body($payload);
        $id = $this->string($payload, 'id');
        $token = $this->string($payload, 'token');

        if ($body === null || $id === null || $token === null) {
            return;
        }

        app(InteractionAcknowledger::class)->acknowledge($id, $token, $body);

        if (($payload['type'] ?? null) !== InteractionAck::MESSAGE_COMPONENT || ($body['type'] ?? null) !== 9) {
            dispatch(new HandleQuoteInteraction($payload));
        }
    }

    /**
     * Messages that mention the bot start a quote capture.
     *
     * @param  array<mixed>  $payload
     */
    private function message(array $payload): void
    {
        $applicationId = config()->string('services.discord.client_id');
        $author = is_array($payload['author'] ?? null) ? $payload['author'] : [];
        $mentions = is_array($payload['mentions'] ?? null) ? $payload['mentions'] : [];

        if ($applicationId === '' || ($author['bot'] ?? false) === true || $this->string($payload, 'guild_id') === null) {
            return;
        }

        $mentionsBot = array_any($mentions, fn (mixed $user): bool => is_array($user) && ($user['id'] ?? null) === $applicationId);

        if ($mentionsBot) {
            dispatch(new CaptureFromMention($payload));
        }
    }

    /**
     * @param  array<mixed>  $data
     */
    private function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function malformed(string $type): void
    {
        Log::warning("Dropping malformed {$type} gateway event.");
    }
}
