<?php

declare(strict_types=1);

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\GuildMember;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('members with access can use the app', function (): void {
    $this->actingAs(User::factory()->member()->create())->get(route('dashboard'))->assertOk();
});

test('guests are sent to the login page', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('members who left their only guild are logged out', function (): void {
    $user = User::factory()->create();
    GuildMember::factory()->for($user->discordUser)->leftGuild()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'access-revoked');

    $this->assertGuest();
});

test('members who lost their role are logged out', function (): void {
    $user = User::factory()->memberOf(Guild::factory()->create(), ['5'])->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('members keep access while another guild still lets them in', function (): void {
    $user = User::factory()->memberOf(Guild::factory()->create(), ['100'])->create();
    GuildMember::factory()->for($user->discordUser)->leftGuild()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('disabling a guild revokes access through it', function (): void {
    $guild = Guild::factory()->create();
    $user = User::factory()->memberOf($guild)->create();
    $guild->update(['enabled' => false]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the admin gate follows admin roles and owners', function (): void {
    config()->set('discord.owner_ids', ['4242']);
    $admin = User::factory()->memberOf(Guild::factory()->create(), ['900'])->create();
    $owner = User::factory()->forDiscordUser(DiscordUser::factory()->create(['discord_id' => '4242']))->create();
    $member = User::factory()->member()->create();

    expect(Gate::forUser($admin)->allows('admin'))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('admin'))->toBeTrue()
        ->and(Gate::forUser($member)->allows('admin'))->toBeFalse();
});

test('only owners may manage guilds', function (): void {
    config()->set('discord.owner_ids', ['4242']);
    $admin = User::factory()->memberOf(Guild::factory()->create(), ['900'])->create();
    $owner = User::factory()->forDiscordUser(DiscordUser::factory()->create(['discord_id' => '4242']))->create();

    expect(Gate::forUser($owner)->allows('manage-guilds'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('manage-guilds'))->toBeFalse();
});

test('horizon is only for admins outside local', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $admin = User::factory()->memberOf(Guild::factory()->create(), ['900'])->create();
    $member = User::factory()->member()->create();

    $this->actingAs($member)->get('/horizon')->assertForbidden();
    $this->actingAs($admin)->get('/horizon')->assertOk();
});
