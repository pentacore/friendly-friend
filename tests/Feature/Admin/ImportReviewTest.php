<?php

declare(strict_types=1);

use App\Enums\QuoteStatus;
use App\Models\Guild;
use App\Models\Quote;
use App\Models\QuoteImport;
use App\Models\User;

beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
    $this->admin = User::factory()->memberOf($this->guild, ['900'])->create();
    $this->import = QuoteImport::query()->create([
        'guild_id' => $this->guild->id,
        'channel_id' => '321',
        'requested_by_discord_user_id' => $this->admin->discord_user_id,
        'status' => 'done',
        'messages_scanned' => 120,
        'quotes_found' => 2,
    ]);
    $this->pending = Quote::factory()->for($this->guild)->withLine('Martin', 'old quote')
        ->count(2)->create(['status' => QuoteStatus::PendingReview, 'import_id' => $this->import->id, 'published_at' => null]);
});

it('lists the imports of servers the admin moderates', function (): void {
    $otherImport = QuoteImport::query()->create([
        'guild_id' => Guild::factory()->create()->id, 'channel_id' => '1',
        'requested_by_discord_user_id' => $this->admin->discord_user_id, 'status' => 'running',
    ]);

    $this->actingAs($this->admin)->get(route('admin.imports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Imports')
            ->has('imports', 1)
            ->where('imports.0.id', $this->import->id)
            ->where('imports.0.pending_count', 2)
            ->where('imports.0.messages_scanned', 120));

    expect($otherImport->exists)->toBeTrue();
});

it('shows the quotes waiting for review', function (): void {
    $this->actingAs($this->admin)->get(route('admin.imports.show', $this->import))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/ImportReview')
            ->has('pending', 2)
            ->where('pending.0.status', 'pending_review')
            ->where('pending.0.can.update', true));
});

it('approves a quote, publishing it with its people', function (): void {
    $quote = $this->pending[0];

    $this->actingAs($this->admin)->post(route('admin.imports.approve', [$this->import, $quote]))->assertRedirect();

    $quote->refresh();
    expect($quote->status)->toBe(QuoteStatus::Published)
        ->and($quote->lines()->sole()->person_id)->not->toBeNull()
        ->and($quote->revisions()->sole()->editor_discord_user_id)->toBe($this->admin->discord_user_id);
});

it('rejects a quote so it is never imported again', function (): void {
    $quote = $this->pending[0];

    $this->actingAs($this->admin)->post(route('admin.imports.reject', [$this->import, $quote]))->assertRedirect();

    expect($quote->refresh()->status)->toBe(QuoteStatus::Removed);
});

it('approves everything that is left', function (): void {
    $this->actingAs($this->admin)->post(route('admin.imports.approve-all', $this->import))->assertRedirect();

    expect(Quote::query()->where('status', QuoteStatus::Published)->count())->toBe(2);
});

it('keeps imports away from members and admins of other servers', function (): void {
    $member = User::factory()->memberOf($this->guild)->create();
    $otherAdmin = User::factory()->memberOf(Guild::factory()->create(), ['900'])->create();

    $this->actingAs($member)->get(route('admin.imports.index'))->assertForbidden();
    $this->actingAs($otherAdmin)->get(route('admin.imports.show', $this->import))->assertForbidden();
    $this->actingAs($otherAdmin)->post(route('admin.imports.approve', [$this->import, $this->pending[0]]))->assertForbidden();
});

it('returns to the review page after editing a pending quote', function (): void {
    $quote = $this->pending[0];

    $this->actingAs($this->admin)
        ->from(route('admin.imports.show', $this->import))
        ->put(route('quotes.update', $quote), ['lines' => [['speaker' => 'Martin', 'text' => 'fixed']], 'context' => null, 'said_at' => null])
        ->assertRedirect(route('admin.imports.show', $this->import));

    expect($quote->refresh()->status)->toBe(QuoteStatus::PendingReview);
});
