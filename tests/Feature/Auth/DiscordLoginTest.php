<?php

declare(strict_types=1);

use App\Models\DiscordMember;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function (): void {
    config()->set('discord.guild_id', '555');
    config()->set('discord.allowed_role_ids', ['100']);
    config()->set('discord.admin_role_ids', ['900']);
    config()->set('services.discord.client_id', 'client-id');
    config()->set('services.discord.client_secret', 'client-secret');
    config()->set('services.discord.redirect', 'http://localhost/auth/discord/callback');
});

function fakeDiscordUser(string $id = '42'): void
{
    Socialite::fake('discord', SocialiteUser::fake([
        'id' => $id,
        'nickname' => 'martin',
        'name' => 'Martin',
        'token' => 'user-token',
    ]));
}

/**
 * @param  list<string>  $roles
 */
function fakeGuildMember(string $id = '42', array $roles = ['100'], int $status = 200): void
{
    Http::fake([
        'discord.com/api/v10/users/@me/guilds/555/member' => $status === 200
            ? Http::response([
                'user' => ['id' => $id, 'username' => 'martin', 'global_name' => 'Martin', 'avatar' => null],
                'nick' => 'Marre',
                'avatar' => null,
                'roles' => $roles,
            ])
            : Http::response(['code' => 10004], $status),
    ]);
}

test('guests see the discord login page', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/Login'));
});

test('the redirect goes to discord with the identify and guild member scopes', function (): void {
    $response = $this->get(route('auth.discord.redirect'));

    $response->assertRedirect();
    $location = (string) $response->headers->get('Location');
    expect($location)->toStartWith('https://discord.com/api/oauth2/authorize')
        ->toContain('client_id=client-id')
        ->toContain('identify')
        ->toContain('guilds.members.read');
});

test('a guild member with an allowed role is logged in', function (): void {
    fakeDiscordUser();
    fakeGuildMember();

    $this->get(route('auth.discord.callback'))
        ->assertRedirect(route('dashboard'));

    $user = User::query()->sole();
    $this->assertAuthenticatedAs($user);
    expect($user->discordMember->discord_id)->toBe('42')
        ->and($user->discordMember->display_name)->toBe('Marre')
        ->and($user->remember_token)->not->toBeNull();
});

test('a non member is turned away', function (): void {
    fakeDiscordUser();
    fakeGuildMember(status: 404);

    $this->get(route('auth.discord.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'not-member');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

test('a member without an allowed role is turned away but still recorded', function (): void {
    fakeDiscordUser();
    fakeGuildMember(roles: ['5']);

    $this->get(route('auth.discord.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'missing-role');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0)
        ->and(DiscordMember::query()->where('discord_id', '42')->exists())->toBeTrue();
});

test('logging in again reuses the user and refreshes the member', function (): void {
    $member = DiscordMember::factory()->withRoles(['100'])->create(['discord_id' => '42', 'username' => 'old']);
    $user = User::factory()->forMember($member)->create();

    fakeDiscordUser();
    fakeGuildMember(roles: ['100', '7']);

    $this->get(route('auth.discord.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(User::query()->count())->toBe(1)
        ->and($member->refresh()->username)->toBe('martin')
        ->and($member->role_ids)->toBe(['100', '7']);
});

test('a failed oauth exchange sends the user back to login', function (): void {
    Socialite::shouldReceive('driver->user')->andThrow(new RuntimeException('invalid state'));

    $this->get(route('auth.discord.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'oauth-failed');

    $this->assertGuest();
});

test('users can log out', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});
