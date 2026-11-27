<?php

declare(strict_types=1);

use App\Enums\QuoteStatus;
use App\Models\Guild;
use App\Models\Person;
use App\Models\Quote;
use App\Models\User;

beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
    $this->submitter = User::factory()->memberOf($this->guild)->create();
    $this->quote = Quote::factory()->for($this->guild)->withLine('Martin', 'old text')->create([
        'submitted_by_discord_user_id' => $this->submitter->discord_user_id,
    ]);
});

function editPayload(array $overrides = []): array
{
    return [
        'lines' => [['speaker' => 'Martin', 'text' => 'I never said that'], ['speaker' => 'Uncle Bo', 'text' => 'You did']],
        'context' => 'at dinner',
        'said_at' => '2026-10-31',
        'said_at_precision' => null,
        ...$overrides,
    ];
}

it('lets the submitter edit, creating people and a revision', function (): void {
    $this->actingAs($this->submitter)->put(route('quotes.update', $this->quote), editPayload())
        ->assertRedirect(route('quotes.show', $this->quote))
        ->assertSessionHasNoErrors();

    $quote = $this->quote->refresh()->load('lines');
    expect($quote->lines->pluck('text')->all())->toBe(['I never said that', 'You did'])
        ->and($quote->lines->every(fn ($line): bool => $line->person_id !== null))->toBeTrue()
        ->and(Person::query()->where('display_name', 'Uncle Bo')->exists())->toBeTrue()
        ->and($quote->context)->toBe('at dinner')
        ->and($quote->said_at?->toDateString())->toBe('2026-10-31')
        ->and($quote->revisions()->count())->toBe(1)
        ->and($quote->search_text)->toContain('Uncle Bo');
});

it('lets the server\'s admins edit and others not', function (): void {
    $admin = User::factory()->memberOf($this->guild, ['900'])->create();
    $member = User::factory()->memberOf($this->guild)->create();

    $this->actingAs($member)->put(route('quotes.update', $this->quote), editPayload())->assertForbidden();
    $this->actingAs($admin)->put(route('quotes.update', $this->quote), editPayload())->assertSessionHasNoErrors();
});

it('validates the lines', function (): void {
    $this->actingAs($this->submitter)
        ->put(route('quotes.update', $this->quote), editPayload(['lines' => [['speaker' => '', 'text' => 'x']]]))
        ->assertSessionHasErrors('lines.0.speaker');

    $this->actingAs($this->submitter)
        ->put(route('quotes.update', $this->quote), editPayload(['lines' => []]))
        ->assertSessionHasErrors('lines');
});

it('lets admins remove a quote from the site', function (): void {
    $admin = User::factory()->memberOf($this->guild, ['900'])->create();

    $this->actingAs($this->submitter)->delete(route('quotes.destroy', $this->quote))->assertForbidden();
    $this->actingAs($admin)->delete(route('quotes.destroy', $this->quote))->assertRedirect(route('quotes.index'));

    expect($this->quote->refresh()->status)->toBe(QuoteStatus::Removed);
    $this->actingAs($this->submitter)->get(route('quotes.show', $this->quote))->assertForbidden();
});
