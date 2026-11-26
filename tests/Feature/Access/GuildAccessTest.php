<?php

declare(strict_types=1);

use App\Access\GuildAccess;
use App\Models\DiscordMember;

beforeEach(function (): void {
    config()->set('discord.allowed_role_ids', ['100', '101']);
    config()->set('discord.admin_role_ids', ['900']);
});

it('allows a member with an allowed role', function (): void {
    $member = DiscordMember::factory()->withRoles(['5', '101'])->make();

    expect(app(GuildAccess::class)->allows($member))->toBeTrue();
});

it('denies a member without an allowed role', function (): void {
    $member = DiscordMember::factory()->withRoles(['5'])->make();

    expect(app(GuildAccess::class)->allows($member))->toBeFalse();
});

it('denies a member who left the guild even with an allowed role', function (): void {
    $member = DiscordMember::factory()->withRoles(['100'])->leftGuild()->make();

    expect(app(GuildAccess::class)->allows($member))->toBeFalse();
});

it('allows any guild member when no allowed roles are configured', function (): void {
    config()->set('discord.allowed_role_ids', []);
    $member = DiscordMember::factory()->withRoles([])->make();

    expect(app(GuildAccess::class)->allows($member))->toBeTrue();
});

it('treats admin roles as allowed and admin', function (): void {
    $member = DiscordMember::factory()->withRoles(['900'])->make();
    $access = app(GuildAccess::class);

    expect($access->allows($member))->toBeTrue()
        ->and($access->isAdmin($member))->toBeTrue();
});

it('is not admin without an admin role', function (): void {
    $member = DiscordMember::factory()->withRoles(['100'])->make();

    expect(app(GuildAccess::class)->isAdmin($member))->toBeFalse();
});

it('is never admin after leaving the guild', function (): void {
    $member = DiscordMember::factory()->withRoles(['900'])->leftGuild()->make();

    expect(app(GuildAccess::class)->isAdmin($member))->toBeFalse();
});
