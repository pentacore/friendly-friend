<?php

declare(strict_types=1);

use App\Models\DiscordMember;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    config()->set('discord.allowed_role_ids', ['100']);
    config()->set('discord.admin_role_ids', ['900']);
});

test('members with access can use the app', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('guests are sent to the login page', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('members who left the guild are logged out', function (): void {
    $member = DiscordMember::factory()->withRoles(['100'])->leftGuild()->create();
    $user = User::factory()->forMember($member)->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'access-revoked');

    $this->assertGuest();
});

test('members who lost their role are logged out', function (): void {
    $member = DiscordMember::factory()->withRoles(['5'])->create();
    $user = User::factory()->forMember($member)->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('the admin gate follows the admin roles', function (): void {
    $admin = User::factory()->forMember(DiscordMember::factory()->withRoles(['900'])->create())->create();
    $member = User::factory()->create();

    expect(Gate::forUser($admin)->allows('admin'))->toBeTrue()
        ->and(Gate::forUser($member)->allows('admin'))->toBeFalse();
});

test('horizon is only for admins outside local', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $admin = User::factory()->forMember(DiscordMember::factory()->withRoles(['900'])->create())->create();
    $member = User::factory()->create();

    $this->actingAs($member)->get('/horizon')->assertForbidden();
    $this->actingAs($admin)->get('/horizon')->assertOk();
});
