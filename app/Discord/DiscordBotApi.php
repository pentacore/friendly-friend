<?php

declare(strict_types=1);

namespace App\Discord;

use App\Discord\Data\GuildMemberData;
use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Discord REST calls made with the bot token.
 */
final readonly class DiscordBotApi
{
    private const int PAGE_SIZE = 1000;

    public function configured(): bool
    {
        return config()->string('discord.bot_token') !== '';
    }

    /**
     * Every member of the guild, paging with Discord's "after" cursor.
     *
     * @return Generator<int, GuildMemberData>
     */
    public function guildMembers(string $guildId): Generator
    {
        $after = '0';

        do {
            $page = $this->request()
                ->get("guilds/{$guildId}/members", ['limit' => self::PAGE_SIZE, 'after' => $after])
                ->throw()
                ->json();

            $rows = is_array($page) ? array_values(array_filter($page, is_array(...))) : [];

            foreach ($rows as $row) {
                $member = GuildMemberData::fromPayload($row);
                $after = $member->discordId;

                yield $member;
            }
        } while (count($rows) === self::PAGE_SIZE);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config()->string('discord.api_base'))
            ->withToken(config()->string('discord.bot_token'), 'Bot')
            ->acceptJson()
            ->retry(3, 1000, throw: false);
    }
}
