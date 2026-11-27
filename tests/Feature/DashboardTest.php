<?php

declare(strict_types=1);

use App\Models\Guild;
use App\Models\Quote;
use App\Models\User;

test('guests are redirected to the login page', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the dashboard shows a random quote and the latest ones', function (): void {
    $guild = Guild::factory()->create();
    $user = User::factory()->memberOf($guild)->create();
    $quotes = Quote::factory()->for($guild)->withLine()->count(7)->create();
    Quote::factory()->for(Guild::factory()->create())->withLine()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('random.guild.id', $guild->id)
            ->has('recent', 5)
            ->where('total', 7));

    expect($quotes)->toHaveCount(7);
});

test('the dashboard works before any quotes exist', function (): void {
    $this->actingAs(User::factory()->member()->create())->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('random', null)->has('recent', 0)->where('total', 0));
});
