<?php

declare(strict_types=1);

use App\Access\GuildAccess;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\GuildMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * @param  list<string>  $roles
 */
function memberIn(Guild $guild, array $roles, ?DiscordUser $user = null): DiscordUser
{
    $user ??= DiscordUser::factory()->create();
    GuildMember::factory()->for($guild)->for($user)->withRoles($roles)->create();

    return $user;
}

it('allows a member with an allowed role in an enabled guild', function (): void {
    $user = memberIn(Guild::factory()->create(), ['5', '100']);

    expect(app(GuildAccess::class)->allows($user))->toBeTrue();
});

it('denies a member without an allowed role', function (): void {
    $user = memberIn(Guild::factory()->create(), ['5']);

    expect(app(GuildAccess::class)->allows($user))->toBeFalse();
});

it('ignores memberships of disabled guilds', function (): void {
    $user = memberIn(Guild::factory()->disabled()->create(), ['100', '900']);
    $access = app(GuildAccess::class);

    expect($access->allows($user))->toBeFalse()
        ->and($access->isAdmin($user))->toBeFalse();
});

it('denies members who left the guild', function (): void {
    $user = DiscordUser::factory()->create();
    GuildMember::factory()->for(Guild::factory()->create())->for($user)->withRoles(['100'])->leftGuild()->create();

    expect(app(GuildAccess::class)->allows($user))->toBeFalse();
});

it('allows any member of a guild that allows all members', function (): void {
    $user = memberIn(Guild::factory()->allowAllMembers()->create(), []);

    expect(app(GuildAccess::class)->allows($user))->toBeTrue();
});

it('allows access through any one of several guilds', function (): void {
    $user = memberIn(Guild::factory()->create(), ['5']);
    memberIn(Guild::factory()->create(['allowed_role_ids' => ['200']]), ['200'], $user);

    expect(app(GuildAccess::class)->allows($user))->toBeTrue();
});

it('uses each guild\'s own role lists', function (): void {
    $guildA = Guild::factory()->create(['allowed_role_ids' => ['100'], 'admin_role_ids' => []]);
    $guildB = Guild::factory()->create(['allowed_role_ids' => ['200'], 'admin_role_ids' => []]);

    // Role 200 belongs to guild B's list; holding it in guild A means nothing.
    $user = memberIn($guildA, ['200']);
    memberIn($guildB, ['100'], $user);

    expect(app(GuildAccess::class)->allows($user))->toBeFalse();
});

it('treats admin roles as allowed and admin', function (): void {
    $user = memberIn(Guild::factory()->create(), ['900']);
    $access = app(GuildAccess::class);

    expect($access->allows($user))->toBeTrue()
        ->and($access->isAdmin($user))->toBeTrue()
        ->and($access->isOwner($user))->toBeFalse();
});

it('lets owners in without any membership and makes them admin', function (): void {
    $user = DiscordUser::factory()->create(['discord_id' => '4242']);
    config()->set('discord.owner_ids', ['4242']);
    $access = app(GuildAccess::class);

    expect($access->allows($user))->toBeTrue()
        ->and($access->isAdmin($user))->toBeTrue()
        ->and($access->isOwner($user))->toBeTrue();
});

it('lists the enabled guilds a user can access', function (): void {
    $allowed = Guild::factory()->create();
    $noRole = Guild::factory()->create();
    $user = memberIn($allowed, ['100']);
    memberIn($noRole, ['5'], $user);
    memberIn(Guild::factory()->disabled()->create(), ['100'], $user);

    expect(app(GuildAccess::class)->accessibleGuildIds($user))->toBe([$allowed->id]);
});

it('loads a user\'s memberships once per user instance', function (): void {
    $guild = Guild::factory()->create();
    $user = User::factory()->memberOf($guild, ['900'])->create();
    $member = $user->discordUser;
    $access = app(GuildAccess::class);

    DB::enableQueryLog();
    $access->allows($member);
    $access->isAdmin($member);
    $access->accessibleGuildIds($member);
    $access->adminGuildIds($member);
    $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'guild_members'));
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1)
        ->and($access->allows($member->fresh() ?? $member))->toBeTrue();
});
