<?php

declare(strict_types=1);

use App\Models\Guild;
use App\Models\Quote;
use App\Models\QuoteMatchup;
use App\Models\User;
use App\Quotes\Actions\RecordMatchup;

beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
    $this->user = User::factory()->memberOf($this->guild)->create();
});

it('shows two different visible quotes', function (): void {
    Quote::factory()->for($this->guild)->withLine()->count(3)->create();
    Quote::factory()->for(Guild::factory()->create())->withLine()->create();

    $this->actingAs($this->user)->get(route('this-or-that.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('quotes/ThisOrThat')
            ->has('pair', 2)
            ->where('pair.0.guild.id', $this->guild->id)
            ->where('pair.1.guild.id', $this->guild->id));
});

it('has no pair with fewer than two quotes', function (): void {
    Quote::factory()->for($this->guild)->withLine()->create();

    $this->actingAs($this->user)->get(route('this-or-that.show'))
        ->assertInertia(fn ($page) => $page->where('pair', null));
});

it('updates Elo ratings with K = 32', function (): void {
    [$a, $b] = Quote::factory()->for($this->guild)->count(2)->create(['elo' => 1000]);

    app(RecordMatchup::class)->handle($this->user, $a, $b, $a);

    expect($a->refresh()->elo)->toBe(1016)
        ->and($b->refresh()->elo)->toBe(984)
        ->and($a->matchup_count)->toBe(1)
        ->and($b->matchup_count)->toBe(1)
        ->and(QuoteMatchup::query()->sole()->winner_quote_id)->toBe($a->id);
});

it('moves an upset more than an expected win', function (): void {
    $strong = Quote::factory()->for($this->guild)->create(['elo' => 1400]);
    $weak = Quote::factory()->for($this->guild)->create(['elo' => 1000]);

    app(RecordMatchup::class)->handle($this->user, $strong, $weak, $weak);

    // Expected score of the weak quote is 1 / (1 + 10^(400/400)) ≈ 0.09, so it gains ≈ 29.
    expect($weak->refresh()->elo)->toBe(1029)
        ->and($strong->refresh()->elo)->toBe(1371);
});

it('records a pick and serves the next pair', function (): void {
    [$a, $b] = Quote::factory()->for($this->guild)->withLine()->count(2)->create();

    $this->actingAs($this->user)
        ->post(route('this-or-that.store'), ['quote_a' => $a->id, 'quote_b' => $b->id, 'winner' => $b->id])
        ->assertRedirect(route('this-or-that.show'));

    expect($b->refresh()->elo)->toBe(1016);
});

it('rejects a winner that was not in the pair, or quotes the user cannot see', function (): void {
    [$a, $b] = Quote::factory()->for($this->guild)->count(2)->create();
    $hidden = Quote::factory()->for(Guild::factory()->create())->create();

    $this->actingAs($this->user)
        ->post(route('this-or-that.store'), ['quote_a' => $a->id, 'quote_b' => $b->id, 'winner' => $hidden->id])
        ->assertSessionHasErrors('winner');

    $this->actingAs($this->user)
        ->post(route('this-or-that.store'), ['quote_a' => $a->id, 'quote_b' => $hidden->id, 'winner' => $a->id])
        ->assertForbidden();
});
