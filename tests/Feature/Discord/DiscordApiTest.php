<?php

declare(strict_types=1);

use App\Discord\Data\GuildMemberData;
use App\Discord\DiscordClient;
use App\Discord\DiscordUserApi;
use App\Discord\HttpDiscordClient;
use App\Discord\Testing\FakeDiscordClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * @return array<string, mixed>
 */
function memberPayload(string $id, array $roles = ['100'], ?string $nick = null): array
{
    return [
        'user' => ['id' => $id, 'username' => "user{$id}", 'global_name' => "Global {$id}", 'avatar' => 'abc'],
        'nick' => $nick,
        'avatar' => null,
        'roles' => $roles,
    ];
}

beforeEach(function (): void {
    config()->set('discord.bot_token', 'bot-token');
});

it('fetches the current user guild member with the oauth token', function (): void {
    Http::fake([
        'discord.com/api/v10/users/@me/guilds/555/member' => Http::response(memberPayload('42', ['100', '7'], 'Marre')),
    ]);

    $member = app(DiscordUserApi::class)->guildMember('user-token', '555');

    expect($member)->toBeInstanceOf(GuildMemberData::class)
        ->and($member?->discordId)->toBe('42')
        ->and($member?->username)->toBe('user42')
        ->and($member?->globalName)->toBe('Global 42')
        ->and($member?->nick)->toBe('Marre')
        ->and($member?->avatar)->toBe('abc')
        ->and($member?->roleIds)->toBe(['100', '7']);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer user-token'));
});

it('lists the ids of the user\'s guilds', function (): void {
    Http::fake(['discord.com/api/v10/users/@me/guilds' => Http::response([['id' => '555', 'name' => 'A'], ['id' => '777', 'name' => 'B']])]);

    expect(app(DiscordUserApi::class)->guildIds('user-token'))->toBe(['555', '777']);
});

it('fetches a guild with its roles using the bot token', function (): void {
    Http::fake(['discord.com/api/v10/guilds/555' => Http::response([
        'id' => '555', 'name' => 'Friends', 'icon' => null,
        'roles' => [['id' => '100', 'name' => 'Friends', 'color' => 1, 'position' => 1, 'managed' => false]],
    ])]);

    $guild = app(HttpDiscordClient::class)->guild('555');

    expect($guild->name)->toBe('Friends')
        ->and($guild->roles[0]->name)->toBe('Friends');
});

it('returns null when the user is not in the guild', function (): void {
    Http::fake(['discord.com/api/v10/users/@me/guilds/555/member' => Http::response(['code' => 10004], 404)]);

    expect(app(DiscordUserApi::class)->guildMember('user-token', '555'))->toBeNull();
});

it('throws on unexpected discord errors', function (): void {
    Http::fake(['discord.com/api/v10/users/@me/guilds/555/member' => Http::response([], 500)]);

    app(DiscordUserApi::class)->guildMember('user-token', '555');
})->throws(RequestException::class);

it('pages through all guild members with the bot token', function (): void {
    $first = array_map(fn (int $i): array => memberPayload((string) (1000 + $i)), range(1, 1000));
    $second = [memberPayload('5000'), memberPayload('5001')];

    Http::fake([
        'discord.com/api/v10/guilds/555/members?limit=1000&after=0' => Http::response($first),
        'discord.com/api/v10/guilds/555/members?limit=1000&after=2000' => Http::response($second),
    ]);

    $members = iterator_to_array(app(HttpDiscordClient::class)->guildMembers('555'), false);

    expect($members)->toHaveCount(1002)
        ->and($members[1001]->discordId)->toBe('5001');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bot bot-token'));
});

it('sends a channel message with the bot token', function (): void {
    Http::fake(['discord.com/api/v10/channels/777/messages' => Http::response(['id' => '1'])]);

    app(HttpDiscordClient::class)->sendMessage('777', 'hello');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://discord.com/api/v10/channels/777/messages'
        && $request['content'] === 'hello'
        && $request->hasHeader('Authorization', 'Bot bot-token'));
});

it('resolves the discord client interface to the http client', function (): void {
    expect(app(DiscordClient::class))->toBeInstanceOf(HttpDiscordClient::class);
});

it('provides a fake client for tests', function (): void {
    $fake = new FakeDiscordClient;
    $member = GuildMemberData::fromPayload(memberPayload('1'));
    $fake->withMembers([$member]);

    $fake->sendMessage('777', 'hi');

    expect(iterator_to_array($fake->guildMembers('555'), false))->toBe([$member])
        ->and($fake->configured())->toBeTrue()
        ->and($fake->sentMessages())->toBe([['channel_id' => '777', 'content' => 'hi']]);
});
