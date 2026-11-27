<?php

declare(strict_types=1);

use App\Discord\Data\GuildMemberData;
use App\Discord\Data\SentMessage;
use App\Discord\DiscordClient;
use App\Discord\DiscordUserApi;
use App\Discord\HttpDiscordClient;
use App\Discord\Testing\FakeDiscordClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

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

it('sends a channel message with the bot token and never pings', function (): void {
    Http::fake(['discord.com/api/v10/channels/777/messages' => Http::response(['id' => '1'])]);

    $id = app(HttpDiscordClient::class)->sendMessage('777', ['content' => 'hello']);

    expect($id)->toBe('1');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://discord.com/api/v10/channels/777/messages'
        && $request['content'] === 'hello'
        && $request['allowed_mentions'] === ['parse' => []]
        && $request->hasHeader('Authorization', 'Bot bot-token'));
});

it('registers guild commands for the application', function (): void {
    config()->set('services.discord.client_id', 'app-1');
    Http::fake(['discord.com/api/v10/applications/app-1/guilds/555/commands' => Http::response([])]);

    app(HttpDiscordClient::class)->registerGuildCommands('555', [['name' => 'quote']]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $request->data() === [['name' => 'quote']]);
});

it('edits the original interaction response without the bot token', function (): void {
    config()->set('services.discord.client_id', 'app-1');
    Http::fake(['discord.com/api/v10/webhooks/app-1/tok/messages/@original' => Http::response([])]);

    app(HttpDiscordClient::class)->editInteractionResponse('tok', ['content' => 'done']);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && $request['content'] === 'done'
        && ! $request->hasHeader('Authorization'));
});

it('sends a direct message, or reports closed DMs', function (): void {
    Http::fake([
        'discord.com/api/v10/users/@me/channels' => Http::response(['id' => 'dm-1']),
        'discord.com/api/v10/channels/dm-1/messages' => Http::sequence()
            ->push(['id' => '5'])
            ->push(['code' => 50007, 'message' => 'Cannot send messages to this user'], 403),
    ]);

    $client = app(HttpDiscordClient::class);

    expect($client->sendDirectMessage('42', ['content' => 'hi']))->toBeInstanceOf(SentMessage::class)
        ->and($client->sendDirectMessage('42', ['content' => 'hi']))->toBeNull();
});

it('resolves the discord client interface to the http client', function (): void {
    expect(app(DiscordClient::class))->toBeInstanceOf(HttpDiscordClient::class);
});

it('provides a fake client for tests', function (): void {
    $fake = new FakeDiscordClient;
    $member = GuildMemberData::fromPayload(memberPayload('1'));
    $fake->withMembers([$member]);

    $fake->sendMessage('777', ['content' => 'hi']);

    expect(iterator_to_array($fake->guildMembers('555'), false))->toBe([$member])
        ->and($fake->configured())->toBeTrue()
        ->and($fake->sentMessages())->toBe([['channel_id' => '777', 'message' => ['content' => 'hi']]]);
    $fake->assertSent('777', 'hi');
});

it('checks whether the bot token is accepted', function (): void {
    Http::fake(['discord.com/api/v10/users/@me' => Http::sequence()
        ->push(['id' => '1', 'username' => 'friendly-bot'])
        ->push(['message' => '401: Unauthorized'], 401)]);

    $client = app(HttpDiscordClient::class);

    expect($client->tokenIsValid())->toBeTrue()
        ->and($client->tokenIsValid())->toBeFalse();
});

it('retries rate limits and server errors but not client errors', function (): void {
    Http::fake([
        'discord.com/api/v10/channels/1/messages' => Http::sequence()
            ->push(['retry_after' => 0], 429)
            ->push([], 502)
            ->push(['id' => '9']),
        'discord.com/api/v10/channels/2/messages' => Http::sequence()
            ->push(['code' => 50013], 403)
            ->push(['id' => 'never']),
    ]);
    Sleep::fake();
    $client = app(HttpDiscordClient::class);

    expect($client->sendMessage('1', ['content' => 'a']))->toBe('9');
    expect(fn () => $client->sendMessage('2', ['content' => 'b']))->toThrow(RequestException::class);
    Http::assertSentCount(4);
});
