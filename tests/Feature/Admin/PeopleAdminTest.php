<?php

declare(strict_types=1);

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\Person;
use App\Models\PersonAlias;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;

beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
    $this->admin = User::factory()->memberOf($this->guild, ['900'])->create();
    $this->member = User::factory()->memberOf($this->guild)->create();
});

it('lists people for admins only', function (): void {
    Person::createNamed('Martin');

    $this->actingAs($this->member)->get(route('admin.people.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.people.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/People')->has('people.data', 1)->where('people.data.0.display_name', 'Martin'));
});

it('adds and removes aliases, case-insensitively unique', function (): void {
    $martin = Person::createNamed('Martin');
    PersonAlias::query()->create(['person_id' => Person::createNamed('Bo')->id, 'alias' => 'Bobo']);

    $this->actingAs($this->admin)->post(route('admin.people.aliases.store', $martin), ['alias' => 'Marre'])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('admin.people.aliases.store', $martin), ['alias' => 'BOBO'])->assertSessionHasErrors('alias');

    $alias = $martin->aliases()->sole();
    expect($alias->alias)->toBe('Marre');

    $this->actingAs($this->admin)->delete(route('admin.people.aliases.destroy', [$martin, $alias]))->assertSessionHasNoErrors();
    expect($martin->aliases()->count())->toBe(0);
});

it('links and unlinks a Discord account', function (): void {
    $martin = Person::createNamed('Martin');
    $account = DiscordUser::factory()->create();

    $this->actingAs($this->admin)->put(route('admin.people.link', $martin), ['discord_user_id' => $account->id])->assertSessionHasNoErrors();
    expect($martin->refresh()->discord_user_id)->toBe($account->id);

    $other = Person::createNamed('Someone');
    $this->actingAs($this->admin)->put(route('admin.people.link', $other), ['discord_user_id' => $account->id])
        ->assertSessionHasErrors('discord_user_id');

    $this->actingAs($this->admin)->put(route('admin.people.link', $martin), ['discord_user_id' => null])->assertSessionHasNoErrors();
    expect($martin->refresh()->discord_user_id)->toBeNull();
});

it('merges a person into another, keeping quotes, aliases and the link', function (): void {
    $into = Person::createNamed('Martin');
    $from = Person::createNamed('Marten');
    $account = DiscordUser::factory()->create();
    $from->update(['discord_user_id' => $account->id]);
    PersonAlias::query()->create(['person_id' => $from->id, 'alias' => 'Marre']);
    $quote = Quote::factory()->for($this->guild)->create();
    QuoteLine::factory()->for($quote)->create(['person_id' => $from->id, 'speaker_name' => 'Marten']);

    $this->actingAs($this->admin)->post(route('admin.people.merge', $from), ['into_person_id' => $into->id])
        ->assertSessionHasNoErrors();

    expect(Person::query()->find($from->id))->toBeNull()
        ->and($quote->lines()->sole()->person_id)->toBe($into->id)
        ->and($into->refresh()->discord_user_id)->toBe($account->id)
        ->and($into->aliases()->pluck('alias')->sort()->values()->all())->toBe(['Marre', 'Marten']);
});

it('refuses to merge a person into themselves', function (): void {
    $martin = Person::createNamed('Martin');

    $this->actingAs($this->admin)->post(route('admin.people.merge', $martin), ['into_person_id' => $martin->id])
        ->assertSessionHasErrors('into_person_id');
});

it('searches names and nicknames literally, closest match first', function (): void {
    Person::createNamed('Martin');
    Person::createNamed('Martina Svensson');
    PersonAlias::query()->create(['person_id' => Person::createNamed('Bo')->id, 'alias' => '100% Bo']);
    $names = fn (string $q): array => collect($this->actingAs($this->admin)->get(route('admin.people.index', ['q' => $q]))
        ->viewData('page')['props']['people']['data'])->pluck('display_name')->all();

    expect($names('%'))->toBe(['Bo'])
        ->and($names('_'))->toBe([])
        ->and($names('martin'))->toBe(['Martin', 'Martina Svensson'])
        ->and($names('100%'))->toBe(['Bo']);
});
