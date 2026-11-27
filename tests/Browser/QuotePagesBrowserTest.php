<?php

declare(strict_types=1);

use App\Models\Guild;
use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteLine;
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
