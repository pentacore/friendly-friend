<?php

declare(strict_types=1);

use App\Enums\QuoteStatus;
use App\Models\Guild;
use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteImport;
use App\Models\QuoteLine;
use App\Models\QuoteMatchup;
use App\Models\User;

/**
 * Renders the quote pages in a real browser, in light and dark mode: no
 * JavaScript errors, content visible. Screenshots land in
 * tests/Browser/Screenshots (git-ignored) for visual review.
 */
beforeEach(function (): void {
    $this->guild = Guild::factory()->create(['name' => 'Claesson Notify']);
    $this->user = User::factory()->memberOf($this->guild)->create();

    $martin = Person::createNamed('Martin');
    $single = Quote::factory()->for($this->guild)->create(['context' => 'at the barbecue', 'said_at' => now()->subDay(), 'said_at_precision' => 'day']);
    QuoteLine::factory()->for($single)->create(['position' => 0, 'speaker_name' => 'Martin', 'person_id' => $martin->id, 'text' => "I'm not drunk, I'm philosophical"]);
    $single->refreshSearchText();

    $conversation = Quote::factory()->for($this->guild)->create();
    QuoteLine::factory()->for($conversation)->create(['position' => 0, 'speaker_name' => 'Bo', 'text' => 'I can totally fix the dishwasher']);
    QuoteLine::factory()->for($conversation)->create(['position' => 1, 'speaker_name' => 'Kim', 'text' => 'You said that about the car']);

    $this->single = $single;
});

it('renders the quote feed', function (bool $dark): void {
    $this->actingAs($this->user);

    $page = visit('/quotes');
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee("I'm not drunk, I'm philosophical")
        ->assertSee('You said that about the car')
        ->assertNoJavaScriptErrors();

    $page->script('document.fonts.ready');
    $page->screenshot(filename: 'quotes-index-'.($dark ? 'dark' : 'light'))
        ->screenshotElement('[data-test^="quote-"] blockquote', 'quote-text-'.($dark ? 'dark' : 'light'));
})->with(['light' => false, 'dark' => true]);

it('searches while typing and marks the matches', function (bool $dark): void {
    $this->actingAs($this->user);

    $page = visit('/quotes');
    $page = $dark ? $page->inDarkMode() : $page;

    $page->type('[aria-label="Search quotes"]', 'philo')
        ->wait(1)
        ->assertDontSee('You said that about the car')
        ->assertSelected('[aria-label="Sort"]', 'best')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector("blockquote mark")?.textContent'))->toBe('philosophical');

    $page->script('document.fonts.ready');
    $page->screenshot(filename: 'quotes-search-'.($dark ? 'dark' : 'light'));
})->with(['light' => false, 'dark' => true]);

it('renders a quote page', function (bool $dark): void {
    $this->actingAs($this->user);

    $page = visit("/quotes/{$this->single->id}");
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee("I'm not drunk, I'm philosophical")
        ->assertSee('at the barbecue')
        ->assertNoJavaScriptErrors();

    $page->script('document.fonts.ready');
    $page->screenshot(filename: 'quotes-show-'.($dark ? 'dark' : 'light'));
})->with(['light' => false, 'dark' => true]);

it('renders a person page on a phone', function (): void {
    $this->actingAs($this->user);

    visit('/people/martin')
        ->resize(390, 844)
        ->assertSee('Martin')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'people-show-phone');
});

it('edits a quote in the browser', function (bool $dark): void {
    $this->single->update(['submitted_by_discord_user_id' => $this->user->discord_user_id]);
    $this->actingAs($this->user);

    $page = visit("/quotes/{$this->single->id}");
    $page = $dark ? $page->inDarkMode() : $page;

    $page->click('Edit')
        ->assertSee('Who said what')
        ->screenshot(filename: 'quotes-edit-'.($dark ? 'dark' : 'light'))
        ->fill('[aria-label="Line 1"]', 'I am a philosopher')
        ->click('Save changes')
        ->assertSee('I am a philosopher')
        ->assertNoJavaScriptErrors();
})->with(['light' => false, 'dark' => true]);

it('votes on a quote', function (): void {
    $this->actingAs($this->user);

    $page = visit("/quotes/{$this->single->id}");

    $page->assertSeeIn('[data-test="score"]', '0')
        ->click('[aria-label="Funny"]')
        ->assertSeeIn('[data-test="score"]', '1')
        ->assertNoJavaScriptErrors();

    expect($this->single->refresh()->score)->toBe(1);

    visit("/quotes/{$this->single->id}")->assertSeeIn('[data-test="score"]', '1');
});

it('plays this or that', function (bool $dark): void {
    $this->actingAs($this->user);

    $page = visit('/this-or-that');
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee('Which one is funnier?')->assertNoJavaScriptErrors();
    $page->script('document.fonts.ready');
    $page->screenshot(filename: 'this-or-that-'.($dark ? 'dark' : 'light'))
        ->click('[data-test="pick-0"]')
        ->assertSee('Which one is funnier?')
        ->assertNoJavaScriptErrors();

    expect(QuoteMatchup::query()->count())->toBe(1);
})->with(['light' => false, 'dark' => true]);

it('shows the top lists', function (bool $dark): void {
    $this->actingAs($this->user);

    $page = visit('/top');
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee('Funniest of all time')
        ->assertSee('Most quoted')
        ->assertNoJavaScriptErrors();
    $page->script('document.fonts.ready');
    $page->screenshot(filename: 'top-'.($dark ? 'dark' : 'light'));
})->with(['light' => false, 'dark' => true]);

it('lets admins manage people', function (bool $dark): void {
    $admin = User::factory()->memberOf($this->guild, ['900'])->create();
    $this->actingAs($admin);

    $page = visit('/admin/people');
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee('Martin')
        ->fill('[aria-label="Nickname for Martin"]', 'Marre')
        ->click('Add')
        ->assertSee('Marre')
        ->assertNoJavaScriptErrors();
    $page->script('document.fonts.ready');
    $page->screenshot(filename: 'admin-people-'.($dark ? 'dark' : 'light'));
})->with(['light' => false, 'dark' => true]);

it('reviews imported quotes', function (bool $dark): void {
    $admin = User::factory()->memberOf($this->guild, ['900'])->create();
    $import = QuoteImport::query()->create([
        'guild_id' => $this->guild->id, 'channel_id' => '321', 'status' => 'done',
        'requested_by_discord_user_id' => $admin->discord_user_id, 'messages_scanned' => 10, 'quotes_found' => 1,
    ]);
    $pending = Quote::factory()->for($this->guild)->withLine('Kim', 'an old classic')
        ->create(['status' => QuoteStatus::PendingReview, 'import_id' => $import->id, 'published_at' => null]);
    $this->actingAs($admin);

    $page = visit('/admin/imports');
    $page = $dark ? $page->inDarkMode() : $page;
    $page->assertSee('Review 1')->click('Review 1')
        ->assertSee('an old classic')
        ->assertNoJavaScriptErrors();
    $page->script('document.fonts.ready');
    $page->screenshot(filename: 'admin-import-review-'.($dark ? 'dark' : 'light'))
        ->click('Approve')
        ->assertSee('All reviewed');

    expect($pending->refresh()->status)->toBe(QuoteStatus::Published);
})->with(['light' => false, 'dark' => true]);

it('shows the dashboard with a random quote', function (bool $dark): void {
    $this->actingAs($this->user);

    $page = visit('/dashboard');
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee('Another one')
        ->assertSee('Latest quotes')
        ->assertNoJavaScriptErrors();
    $page->script('document.fonts.ready');
    $page->screenshot(filename: 'dashboard-'.($dark ? 'dark' : 'light'))
        ->click('Another one')
        ->assertNoJavaScriptErrors();
})->with(['light' => false, 'dark' => true]);
