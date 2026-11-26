<?php

declare(strict_types=1);

use App\Access\Jobs\MarkMemberLeft;
use App\Access\Jobs\UpsertGuildMember;
use App\Discord\Data\GuildMemberData;
use App\Models\DiscordMember;

it('upserts a member from gateway data', function (): void {
    dispatch_sync(new UpsertGuildMember(new GuildMemberData('42', 'martin', 'Martin', 'Marre', null, null, ['100'])));

    expect(DiscordMember::query()->where('discord_id', '42')->sole()->display_name)->toBe('Marre');
});

it('marks a member as having left the guild', function (): void {
    $member = DiscordMember::factory()->create(['discord_id' => '42']);

    dispatch_sync(new MarkMemberLeft('42'));

    expect($member->refresh()->in_guild)->toBeFalse();
});

it('ignores leave events for unknown members', function (): void {
    dispatch_sync(new MarkMemberLeft('404'));

    expect(DiscordMember::query()->count())->toBe(0);
});
