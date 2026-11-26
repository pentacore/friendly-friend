<?php

declare(strict_types=1);

use App\Models\DiscordMember;

beforeEach(function (): void {
    config()->set('discord.guild_id', '555');
});

it('prefers the guild nickname, then global name, then username', function (): void {
    expect(DiscordMember::factory()->make(['guild_nick' => 'Marre', 'global_name' => 'Martin'])->display_name)->toBe('Marre')
        ->and(DiscordMember::factory()->make(['guild_nick' => null, 'global_name' => 'Martin'])->display_name)->toBe('Martin')
        ->and(DiscordMember::factory()->make(['guild_nick' => null, 'global_name' => null, 'username' => 'martin'])->display_name)->toBe('martin');
});

it('uses the guild avatar, then the user avatar', function (): void {
    $member = DiscordMember::factory()->make(['discord_id' => '42', 'avatar' => 'abc', 'guild_avatar' => 'def']);
    expect($member->avatarUrl())->toBe('https://cdn.discordapp.com/guilds/555/users/42/avatars/def.png');

    $member->guild_avatar = null;
    expect($member->avatarUrl())->toBe('https://cdn.discordapp.com/avatars/42/abc.png');
});

it('falls back to the default avatar chosen by (snowflake >> 22) % 6', function (): void {
    // 175928847299117063 >> 22 = 41944705796, % 6 = 2
    $member = DiscordMember::factory()->make(['discord_id' => '175928847299117063', 'avatar' => null]);

    expect($member->avatarUrl())->toBe('https://cdn.discordapp.com/embed/avatars/2.png');
});
