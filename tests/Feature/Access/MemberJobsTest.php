<?php

declare(strict_types=1);

use App\Access\Jobs\MarkMemberLeft;
use App\Access\Jobs\UpsertGuildMember;
use App\Discord\Data\GuildMemberData;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\GuildMember;

function memberData(string $id = '42', array $roles = ['100']): GuildMemberData
{
    return new GuildMemberData($id, 'martin', 'Martin', 'Marre', 'abc', null, $roles);
}

it('records the account and the membership of an enabled guild', function (): void {
    $guild = Guild::factory()->create(['discord_id' => '555']);

    dispatch_sync(new UpsertGuildMember('555', memberData()));

    $member = GuildMember::query()->with('discordUser')->sole();
    expect($member->guild_id)->toBe($guild->id)
        ->and($member->display_name)->toBe('Marre')
        ->and($member->role_ids)->toBe(['100'])
        ->and($member->discordUser->discord_id)->toBe('42')
        ->and($member->discordUser->avatar)->toBe('abc');
});

it('keeps one account across guilds', function (): void {
    Guild::factory()->create(['discord_id' => '555']);
    Guild::factory()->create(['discord_id' => '777']);

    dispatch_sync(new UpsertGuildMember('555', memberData()));
    dispatch_sync(new UpsertGuildMember('777', memberData(roles: ['200'])));

    expect(DiscordUser::query()->count())->toBe(1)
        ->and(GuildMember::query()->count())->toBe(2);
});

it('ignores members of disabled or unknown guilds', function (): void {
    Guild::factory()->disabled()->create(['discord_id' => '555']);

    dispatch_sync(new UpsertGuildMember('555', memberData()));
    dispatch_sync(new UpsertGuildMember('999', memberData()));

    expect(GuildMember::query()->count())->toBe(0);
});

it('marks a member as gone from one guild only', function (): void {
    $user = DiscordUser::factory()->create(['discord_id' => '42']);
    $left = GuildMember::factory()->for(Guild::factory()->create(['discord_id' => '555']))->for($user)->create();
    $stays = GuildMember::factory()->for(Guild::factory()->create(['discord_id' => '777']))->for($user)->create();

    dispatch_sync(new MarkMemberLeft('555', '42'));

    expect($left->refresh()->in_guild)->toBeFalse()
        ->and($stays->refresh()->in_guild)->toBeTrue();
});
