<?php

declare(strict_types=1);

namespace App\Discord;

use App\Discord\Data\GuildMemberData;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Discord REST calls made with a logged-in user's OAuth access token.
 */
final readonly class DiscordUserApi
{
    /**
     * IDs of every guild the user is in (needs the `guilds` scope).
     *
     * @return list<string>
     *
     * @throws RequestException
     */
    public function guildIds(string $accessToken): array
    {
        $payload = Http::baseUrl(config()->string('discord.api_base'))
            ->withToken($accessToken)
            ->acceptJson()
            ->get('users/@me/guilds')
            ->throw()
            ->json();

        $guilds = is_array($payload) ? array_filter($payload, is_array(...)) : [];

        return array_values(array_filter(
            array_map(fn (array $guild): mixed => $guild['id'] ?? null, $guilds),
            is_string(...),
        ));
    }

    /**
     * The user's membership of the guild, or null if they are not a member.
     *
     * @throws RequestException on any Discord error other than "not a member"
     */
    public function guildMember(string $accessToken, string $guildId): ?GuildMemberData
    {
        $response = Http::baseUrl(config()->string('discord.api_base'))
            ->withToken($accessToken)
            ->acceptJson()
            ->get("users/@me/guilds/{$guildId}/member");

        if ($response->notFound()) {
            return null;
        }

        $payload = $response->throw()->json();

        return GuildMemberData::fromPayload(is_array($payload) ? $payload : []);
    }
}
