<?php

declare(strict_types=1);

namespace App\Discord\Gateway;

use App\Access\Jobs\MarkMemberLeft;
use App\Access\Jobs\UpsertGuildMember;
use App\Discord\Data\DiscordUserData;
use App\Discord\Data\GuildData;
use App\Discord\Data\GuildMemberData;
use App\Discord\Data\Payload;
use App\Discord\Interactions\EventCustomId;
use App\Discord\Interactions\InteractionAck;
use App\Discord\Interactions\InteractionAcknowledger;
use App\Discord\Interactions\PrivacyCommands;
use App\Discord\Jobs\MarkGuildLeft;
use App\Discord\Jobs\RefreshGuild;
use App\Discord\Jobs\RegisterGuild;
use App\Planning\Jobs\HandleEventInteraction;
use App\Quotes\Jobs\CaptureFromMention;
use App\Quotes\Jobs\HandleQuoteInteraction;
use App\Quotes\Jobs\PickUpPostedQuote;
use App\Quotes\WatchedChannels;
use App\Stats\Jobs\HandlePrivacyCommand;
use App\Stats\Jobs\IngestMessage;
use App\Stats\Jobs\IngestPresence;
use App\Stats\Jobs\IngestReaction;
use App\Stats\Jobs\IngestVoiceState;
use App\Stats\Jobs\SeedGuildSessions;
use App\Stats\OptedOutMembers;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

/**
 * Turns gateway dispatch events (type + decoded `d` payload) into queued
 * jobs. Runs inside the bot process, so it must stay cheap: no database, no
 * HTTP, no business logic. Whether a guild is enabled is the jobs' concern.
 * Stats events from bots and opted-out members (cached set) are dropped here.
 */
final readonly class GatewayEventRouter
{
    /** Default messages and replies; joins, pins, boosts… are not chat. */
    private const array COUNTED_MESSAGE_TYPES = [0, 19];

    public function __construct(private OptedOutMembers $optedOut, private WatchedChannels $watchedChannels) {}

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
            'MESSAGE_REACTION_ADD' => $this->reaction($payload),
            'VOICE_STATE_UPDATE' => $this->voiceState($payload),
            'PRESENCE_UPDATE' => $this->presence($payload),
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

        if ($syncMembers) {
            $this->seedSessions($guild->discordId, $payload);
        }
    }

    /**
     * GUILD_CREATE carries the current voice states and presences.
     *
     * @param  array<mixed>  $payload
     */
    private function seedSessions(string $guildId, array $payload): void
    {
        $voice = [];

        foreach (is_array($payload['voice_states'] ?? null) ? $payload['voice_states'] : [] as $state) {
            $userId = is_array($state) ? $this->string($state, 'user_id') : null;
            $channelId = is_array($state) ? $this->string($state, 'channel_id') : null;

            if ($userId !== null && $channelId !== null && ! $this->isBot($state['member'] ?? null) && ! $this->optedOut->contains($userId)) {
                $voice[$userId] = $channelId;
            }
        }

        $playing = [];

        foreach (is_array($payload['presences'] ?? null) ? $payload['presences'] : [] as $presence) {
            $userId = is_array($presence) && is_array($presence['user'] ?? null) ? $this->string($presence['user'], 'id') : null;
            $games = is_array($presence) ? $this->playing($presence['activities'] ?? null) : [];

            if ($userId !== null && $games !== [] && ! $this->optedOut->contains($userId)) {
                $playing[$userId] = $games;
            }
        }

        dispatch(new SeedGuildSessions($guildId, $voice, $playing, $this->now()));
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

        if (($payload['type'] ?? null) === InteractionAck::MESSAGE_COMPONENT && EventCustomId::parse(Payload::string($payload, 'data', 'custom_id')) instanceof EventCustomId) {
            dispatch(new HandleEventInteraction($payload));

            return;
        }

        if (($payload['type'] ?? null) === InteractionAck::APPLICATION_COMMAND && Payload::string($payload, 'data', 'name') === PrivacyCommands::SLASH) {
            dispatch(new HandlePrivacyCommand($payload));

            return;
        }

        if (($payload['type'] ?? null) !== InteractionAck::MESSAGE_COMPONENT || ($body['type'] ?? null) !== 9) {
            dispatch(new HandleQuoteInteraction($payload));
        }
    }

    /**
     * Messages by people in a guild are counted; those that mention the bot
     * also start a quote capture, and those in a watched channel are checked
     * for a quote posted without the bot.
     *
     * @param  array<mixed>  $payload
     */
    private function message(array $payload): void
    {
        $applicationId = config()->string('services.discord.client_id');
        $author = is_array($payload['author'] ?? null) ? $payload['author'] : [];
        $mentions = is_array($payload['mentions'] ?? null) ? $payload['mentions'] : [];

        if (($author['bot'] ?? false) === true || $this->string($payload, 'guild_id') === null) {
            return;
        }

        $mentionsBot = $applicationId !== '' && array_any($mentions, fn (mixed $user): bool => is_array($user) && ($user['id'] ?? null) === $applicationId);

        if ($mentionsBot) {
            dispatch(new CaptureFromMention($payload));
        } elseif (($channelId = $this->string($payload, 'channel_id')) !== null && $this->watchedChannels->contains($channelId)) {
            dispatch(new PickUpPostedQuote($payload));
        }

        $this->countMessage($payload, $author);
    }

    /**
     * Counted for stats: chat messages by people (no webhooks), never the content.
     *
     * @param  array<mixed>  $payload
     * @param  array<mixed>  $author
     */
    private function countMessage(array $payload, array $author): void
    {
        $guildId = $this->string($payload, 'guild_id');
        $channelId = $this->string($payload, 'channel_id');
        $sentAt = $this->string($payload, 'timestamp');
        $user = DiscordUserData::fromPayload($author);

        if ($guildId === null || $channelId === null || $sentAt === null || ! $user->isValid()
            || isset($payload['webhook_id'])
            || ! in_array($payload['type'] ?? 0, self::COUNTED_MESSAGE_TYPES, true)
            || $this->optedOut->contains($user->discordId)) {
            return;
        }

        dispatch(new IngestMessage($guildId, $user, $channelId, $sentAt));
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function reaction(array $payload): void
    {
        $guildId = $this->string($payload, 'guild_id');
        $userId = $this->string($payload, 'user_id');
        $emoji = is_array($payload['emoji'] ?? null) ? $payload['emoji'] : [];
        $emojiName = $this->string($emoji, 'name');
        $emojiId = $this->string($emoji, 'id');

        if ($guildId === null || $userId === null || $emojiName === null || $this->isBot($payload['member'] ?? null) || $this->optedOut->contains($userId)) {
            return;
        }

        dispatch(new IngestReaction(
            $guildId,
            $userId,
            $this->string($payload, 'message_author_id'),
            $emojiId !== null ? "{$emojiId}:{$emojiName}" : $emojiName,
            $this->now(),
        ));
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function voiceState(array $payload): void
    {
        $guildId = $this->string($payload, 'guild_id');
        $userId = $this->string($payload, 'user_id');

        if ($guildId === null || $userId === null || $this->isBot($payload['member'] ?? null) || $this->optedOut->contains($userId)) {
            return;
        }

        dispatch(new IngestVoiceState($guildId, $userId, $this->string($payload, 'channel_id'), $this->now()));
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function presence(array $payload): void
    {
        $guildId = $this->string($payload, 'guild_id');
        $user = is_array($payload['user'] ?? null) ? $payload['user'] : [];
        $userId = $this->string($user, 'id');

        if ($guildId === null || $userId === null || ($user['bot'] ?? false) === true || $this->optedOut->contains($userId)) {
            return;
        }

        $games = ($payload['status'] ?? null) === 'offline' ? [] : $this->playing($payload['activities'] ?? null);

        dispatch(new IngestPresence($guildId, $userId, $games, $this->now()));
    }

    /**
     * Activities of the tracked types ("Playing …"), as name => type.
     *
     * @return array<string, int>
     */
    private function playing(mixed $activities): array
    {
        $types = config()->array('stats.activity_types');
        $playing = [];

        foreach (is_array($activities) ? $activities : [] as $activity) {
            $name = is_array($activity) ? $this->string($activity, 'name') : null;
            $type = is_array($activity) ? ($activity['type'] ?? null) : null;

            if ($name !== null && is_int($type) && in_array($type, $types, true)) {
                $playing[$name] = $type;
            }
        }

        return $playing;
    }

    private function isBot(mixed $member): bool
    {
        return is_array($member) && is_array($member['user'] ?? null) && ($member['user']['bot'] ?? false) === true;
    }

    private function now(): string
    {
        return Date::now()->toIso8601String();
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
