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
