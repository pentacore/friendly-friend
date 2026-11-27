<?php

declare(strict_types=1);

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\GuildMember;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function (): void {
    config()->set('services.discord.client_id', 'client-id');
    config()->set('services.discord.client_secret', 'client-secret');
    config()->set('services.discord.redirect', 'http://localhost/auth/discord/callback');
});

function fakeDiscordLogin(string $id = '42'): void
{
    Socialite::fake('discord', SocialiteUser::fake([
        'id' => $id,
        'nickname' => 'martin',
        'name' => 'Martin',
        'token' => 'user-token',
    ])->setRaw(['id' => $id, 'username' => 'martin', 'global_name' => 'Martin', 'avatar' => 'abc']));
}

/**
 * @param  array<string, list<string>|null>  $memberships  guild discord id => roles (null = not a member)
 */
function fakeDiscordGuilds(array $memberships, string $userId = '42'): void
{
    $fakes = [
        'discord.com/api/v10/users/@me/guilds' => Http::response(array_map(
            fn (string $id): array => ['id' => $id, 'name' => "Guild {$id}"],
            array_keys(array_filter($memberships, fn (?array $roles): bool => $roles !== null)),
        )),
    ];

    foreach ($memberships as $guildId => $roles) {
        $fakes["discord.com/api/v10/users/@me/guilds/{$guildId}/member"] = $roles === null
            ? Http::response(['code' => 10004], 404)
            : Http::response([
                'user' => ['id' => $userId, 'username' => 'martin', 'global_name' => 'Martin', 'avatar' => 'abc'],
                'nick' => "Nick in {$guildId}",
                'avatar' => null,
                'roles' => $roles,
            ]);
    }

    Http::fake($fakes);
}

test('guests see the discord login page', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/Login'));
});

test('the redirect asks discord for identify, guilds and guild member scopes', function (): void {
    $response = $this->get(route('auth.discord.redirect'));

    $location = (string) $response->assertRedirect()->headers->get('Location');
    expect($location)->toStartWith('https://discord.com/api/oauth2/authorize')
        ->toContain('client_id=client-id')
        ->toContain('scope=identify+guilds+guilds.members.read');
});

test('a member with an allowed role in an enabled guild is logged in', function (): void {
    $guild = Guild::factory()->create(['discord_id' => '555']);
    fakeDiscordLogin();
    fakeDiscordGuilds(['555' => ['100']]);

    $this->get(route('auth.discord.callback'))->assertRedirect(route('dashboard'));

    $user = User::query()->sole();
    $this->assertAuthenticatedAs($user);
    expect($user->discordUser->discord_id)->toBe('42')
        ->and($user->discordUser->display_name)->toBe('Martin')
        ->and($user->discordUser->memberships()->sole()->guild_id)->toBe($guild->id)
        ->and($user->remember_token)->not->toBeNull();
});

test('access through one of several guilds is enough', function (): void {
    Guild::factory()->create(['discord_id' => '555']);
    Guild::factory()->create(['discord_id' => '777', 'allowed_role_ids' => ['200']]);
    fakeDiscordLogin();
    fakeDiscordGuilds(['555' => ['5'], '777' => ['200']]);

    $this->get(route('auth.discord.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    expect(GuildMember::query()->count())->toBe(2);
});

test('someone in no enabled guild is turned away as not a member', function (): void {
    Guild::factory()->create(['discord_id' => '555']);
    Guild::factory()->disabled()->create(['discord_id' => '777']);
    fakeDiscordLogin();
    fakeDiscordGuilds(['555' => null, '777' => ['100']]);

    $this->get(route('auth.discord.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'not-member');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0)
        ->and(GuildMember::query()->count())->toBe(0);
});

test('a member without an allowed role is turned away but still recorded', function (): void {
    Guild::factory()->create(['discord_id' => '555']);
    fakeDiscordLogin();
    fakeDiscordGuilds(['555' => ['5']]);

    $this->get(route('auth.discord.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'missing-role');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0)
        ->and(GuildMember::query()->count())->toBe(1);
});

test('owners get in without being in any guild', function (): void {
    config()->set('discord.owner_ids', ['42']);
    fakeDiscordLogin();
    fakeDiscordGuilds([]);

    $this->get(route('auth.discord.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});

test('a guild the user has left is marked as left at login', function (): void {
    $guild = Guild::factory()->create(['discord_id' => '555']);
    $other = Guild::factory()->create(['discord_id' => '777']);
    $discordUser = DiscordUser::factory()->create(['discord_id' => '42']);
    $stale = GuildMember::factory()->for($guild)->for($discordUser)->create();
    fakeDiscordLogin();
    fakeDiscordGuilds(['777' => ['100']]);

    $this->get(route('auth.discord.callback'))->assertRedirect(route('dashboard'));

    expect($stale->refresh()->in_guild)->toBeFalse()
        ->and(GuildMember::query()->whereBelongsTo($other)->sole()->in_guild)->toBeTrue();
});

test('logging in again reuses the user and refreshes the account', function (): void {
    Guild::factory()->create(['discord_id' => '555']);
    $discordUser = DiscordUser::factory()->create(['discord_id' => '42', 'username' => 'old']);
    $user = User::factory()->forDiscordUser($discordUser)->create();
    fakeDiscordLogin();
    fakeDiscordGuilds(['555' => ['100', '7']]);

    $this->get(route('auth.discord.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(User::query()->count())->toBe(1)
        ->and($discordUser->refresh()->username)->toBe('martin')
        ->and($discordUser->memberships()->sole()->role_ids)->toBe(['100', '7']);
});

test('a failed oauth exchange sends the user back to login', function (): void {
    Socialite::shouldReceive('driver->user')->andThrow(new RuntimeException('invalid state'));

    $this->get(route('auth.discord.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'oauth-failed');

    $this->assertGuest();
});

test('users can log out', function (): void {
    $user = User::factory()->member()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});
