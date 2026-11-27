<?php

declare(strict_types=1);

use App\Access\Jobs\SyncGuildMembers;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\GuildMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

function owner(): User
{
    config()->set('discord.owner_ids', ['4242']);

    return User::factory()->forDiscordUser(DiscordUser::factory()->create(['discord_id' => '4242']))->create();
}

/**
 * @return array<string, mixed>
 */
function guildRoles(): array
{
    return ['roles' => [
        ['id' => '555', 'name' => '@everyone', 'color' => 0, 'position' => 0, 'managed' => false],
        ['id' => '100', 'name' => 'Friends', 'color' => 3447003, 'position' => 1, 'managed' => false],
        ['id' => '300', 'name' => 'Some Bot', 'color' => 0, 'position' => 2, 'managed' => true],
        ['id' => '900', 'name' => 'Admin', 'color' => 15158332, 'position' => 3, 'managed' => false],
    ]];
}

test('owners see every server with its assignable roles', function (): void {
    $guild = Guild::factory()->disabled()->create(['discord_id' => '555', 'name' => 'Friends', ...guildRoles()]);
    GuildMember::factory()->for($guild)->count(2)->create();
    GuildMember::factory()->for($guild)->leftGuild()->create();

    $this->actingAs(owner())->get(route('admin.guilds.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Guilds')
            ->has('guilds', 1)
            ->where('guilds.0.name', 'Friends')
            ->where('guilds.0.enabled', false)
            ->where('guilds.0.member_count', 2)
            // @everyone and bot-managed roles can't be picked; highest role first.
            ->where('guilds.0.roles.0.name', 'Admin')
            ->where('guilds.0.roles.1.name', 'Friends')
            ->has('guilds.0.roles', 2));
});

test('server admins and members may not manage servers', function (): void {
    $admin = User::factory()->memberOf(Guild::factory()->create(), ['900'])->create();

    $this->actingAs($admin)->get(route('admin.guilds.index'))->assertForbidden();
    $this->actingAs(User::factory()->member()->create())->get(route('admin.guilds.index'))->assertForbidden();
});

test('owners can enable a server and choose its roles', function (): void {
    Queue::fake();
    $guild = Guild::factory()->disabled()->create(['discord_id' => '555', 'allowed_role_ids' => [], 'admin_role_ids' => [], ...guildRoles()]);

    $this->actingAs(owner())
        ->put(route('admin.guilds.update', $guild), [
            'enabled' => true,
            'allow_all_members' => false,
            'allowed_role_ids' => ['100'],
            'admin_role_ids' => ['900'],
        ])
        ->assertRedirect(route('admin.guilds.index'))
        ->assertSessionHasNoErrors();

    $guild->refresh();
    expect($guild->enabled)->toBeTrue()
        ->and($guild->allowed_role_ids)->toBe(['100'])
        ->and($guild->admin_role_ids)->toBe(['900']);
    Queue::assertPushed(SyncGuildMembers::class, fn (SyncGuildMembers $job): bool => $job->guildDiscordId === '555');
});

test('saving an already enabled server does not resync members', function (): void {
    Queue::fake();
    $guild = Guild::factory()->create(guildRoles());

    $this->actingAs(owner())->put(route('admin.guilds.update', $guild), [
        'enabled' => true,
        'allow_all_members' => true,
        'allowed_role_ids' => [],
        'admin_role_ids' => [],
    ])->assertSessionHasNoErrors();

    expect($guild->refresh()->allow_all_members)->toBeTrue();
    Queue::assertNotPushed(SyncGuildMembers::class);
});

test('roles must belong to the server and be assignable', function (string $roleId): void {
    $guild = Guild::factory()->create(['discord_id' => '555', ...guildRoles()]);

    $this->actingAs(owner())->put(route('admin.guilds.update', $guild), [
        'enabled' => true,
        'allow_all_members' => false,
        'allowed_role_ids' => [$roleId],
        'admin_role_ids' => [],
    ])->assertSessionHasErrors('allowed_role_ids.0');
})->with(['unknown role' => '12345', '@everyone' => '555', 'bot-managed role' => '300']);

test('members cannot update servers', function (): void {
    $guild = Guild::factory()->create();

    $this->actingAs(User::factory()->member()->create())
        ->put(route('admin.guilds.update', $guild), ['enabled' => false, 'allow_all_members' => false])
        ->assertForbidden();
});

test('the server list does not query member counts per server', function (): void {
    Guild::factory()->count(3)->create();
    $owner = owner();
    DB::enableQueryLog();

    $this->actingAs($owner)->get(route('admin.guilds.index'))->assertOk();

    $countQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'count(*)'));
    expect($countQueries)->toHaveCount(1);
});

test('owners pick the channel where saved quotes are posted', function (): void {
    $guild = Guild::factory()->create(['discord_id' => '555', 'channels' => [['id' => '777', 'name' => 'quotes', 'position' => 1]], ...guildRoles()]);
    $payload = ['enabled' => true, 'allow_all_members' => false, 'allowed_role_ids' => ['100'], 'admin_role_ids' => []];
    $owner = owner();

    $this->actingAs($owner)->put(route('admin.guilds.update', $guild), [...$payload, 'quotes_channel_id' => '999'])
        ->assertSessionHasErrors('quotes_channel_id');

    $this->actingAs($owner)->put(route('admin.guilds.update', $guild), [...$payload, 'quotes_channel_id' => '777'])
        ->assertSessionHasNoErrors();
    expect($guild->refresh()->quotes_channel_id)->toBe('777');

    $this->actingAs($owner)->put(route('admin.guilds.update', $guild), [...$payload, 'quotes_channel_id' => null]);
    expect($guild->refresh()->quotes_channel_id)->toBeNull();
});
