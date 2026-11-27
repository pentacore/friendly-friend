<?php

declare(strict_types=1);

use App\Enums\QuoteStatus;
use App\Models\Guild;
use App\Models\Quote;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
    $this->admin = User::factory()->memberOf($this->guild, ['900'])->create();
    $this->member = User::factory()->memberOf($this->guild)->create();
    $this->quote = Quote::factory()->for($this->guild)->withLine('Bo', 'cheese is a vegetable')->create(['status' => QuoteStatus::Removed, 'score' => 4]);
});

it('lists removed quotes for admins of that server', function (): void {
    Quote::factory()->for(Guild::factory()->create())->withLine('X', 'elsewhere')->create(['status' => QuoteStatus::Removed]);

    $this->actingAs($this->admin)->get(route('admin.removed-quotes.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->component('admin/RemovedQuotes')
            ->has('quotes.data', 1)
            ->where('quotes.data.0.can.restore', true));

    $this->actingAs($this->member)->get(route('admin.removed-quotes.index'))->assertForbidden();
});

it('shows a removed quote to admins only', function (): void {
    $this->actingAs($this->admin)->get(route('quotes.show', $this->quote))->assertOk();
    $this->actingAs($this->member)->get(route('quotes.show', $this->quote))->assertForbidden();
});

it('restores a quote with its score', function (): void {
    $this->actingAs($this->member)->post(route('quotes.restore', $this->quote))->assertForbidden();
    $this->actingAs($this->admin)->post(route('quotes.restore', $this->quote))->assertRedirect(route('quotes.show', $this->quote));

    expect($this->quote->fresh())->status->toBe(QuoteStatus::Published)->score->toBe(4);
    $this->actingAs($this->admin)->post(route('quotes.restore', $this->quote))->assertForbidden();
});
