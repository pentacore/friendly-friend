<?php

declare(strict_types=1);

use App\Access\Actions\UpsertDiscordMember;
use App\Discord\Data\GuildMemberData;
use App\Models\DiscordMember;

it('creates a member from discord data', function (): void {
    $member = app(UpsertDiscordMember::class)->handle(new GuildMemberData(
        discordId: '42',
        username: 'martin',
        globalName: 'Martin',
        nick: 'Marre',
        avatar: 'abc',
        guildAvatar: null,
        roleIds: ['100'],
    ));

    expect($member->exists)->toBeTrue()
        ->and($member->display_name)->toBe('Marre')
        ->and($member->role_ids)->toBe(['100'])
        ->and($member->in_guild)->toBeTrue();
});

it('updates an existing member by discord id', function (): void {
    DiscordMember::factory()->leftGuild()->create(['discord_id' => '42', 'username' => 'old', 'role_ids' => []]);

    app(UpsertDiscordMember::class)->handle(new GuildMemberData('42', 'new', null, null, null, null, ['7']));

    expect(DiscordMember::query()->count())->toBe(1);
    $member = DiscordMember::query()->sole();
    expect($member->username)->toBe('new')
        ->and($member->role_ids)->toBe(['7'])
        ->and($member->in_guild)->toBeTrue();
});
