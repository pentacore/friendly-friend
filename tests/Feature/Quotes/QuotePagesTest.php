<?php

declare(strict_types=1);

use App\Models\Guild;
use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteRevision;
use App\Models\User;

beforeEach(function (): void {
    $this->guild = Guild::factory()->create(['name' => 'Friends']);
    $this->user = User::factory()->memberOf($this->guild)->create();
});

function savedQuote(Guild $guild, string $speaker, string $text, array $attributes = []): Quote
{
    $quote = Quote::factory()->for($guild)->withLine($speaker, $text)->create($attributes);
    $quote->refreshSearchText();

    return $quote;
}

it('lists visible quotes newest first with the filters', function (): void {
    $old = savedQuote($this->guild, 'Martin', 'old one', ['published_at' => now()->subDay()]);
    $new = savedQuote($this->guild, 'Bo', 'new one');
    savedQuote(Guild::factory()->create(), 'Kim', 'other server');

    $this->actingAs($this->user)->get(route('quotes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('quotes/Index')
            ->has('quotes.data', 2)
            ->where('quotes.data.0.id', $new->id)
            ->where('quotes.data.1.id', $old->id)
            ->where('filters', ['q' => '', 'guild' => null, 'person' => null])
            ->where('guilds', [['id' => $this->guild->id, 'name' => 'Friends']]));
});

it('searches speakers, lines and context', function (): void {
    savedQuote($this->guild, 'Martin', 'cheese is a vegetable');
    savedQuote($this->guild, 'Bo', 'I can fix it', ['context' => 'the dishwasher']);

    $this->actingAs($this->user)->get(route('quotes.index', ['q' => 'dishwasher']))
        ->assertInertia(fn ($page) => $page->has('quotes.data', 1)->where('quotes.data.0.lines.0.speaker_name', 'Bo'));

    $this->actingAs($this->user)->get(route('quotes.index', ['q' => 'martin']))
        ->assertInertia(fn ($page) => $page->has('quotes.data', 1));
});

it('filters by person', function (): void {
    $martin = Person::createNamed('Martin');
    $quote = savedQuote($this->guild, 'Martin', 'hi');
    QuoteLine::query()->where('quote_id', $quote->id)->update(['person_id' => $martin->id]);
    savedQuote($this->guild, 'Bo', 'hello');

    $this->actingAs($this->user)->get(route('quotes.index', ['person' => $martin->slug]))
        ->assertInertia(fn ($page) => $page
            ->has('quotes.data', 1)
            ->where('person', ['slug' => $martin->slug, 'display_name' => 'Martin']));
});

it('pages the feed for infinite scroll', function (): void {
    foreach (range(1, 25) as $i) {
        savedQuote($this->guild, 'Martin', "quote {$i}", ['published_at' => now()->subMinutes($i)]);
    }

    $this->actingAs($this->user)->get(route('quotes.index'))
        ->assertInertia(fn ($page) => $page->has('quotes.data', 20));

    $this->actingAs($this->user)->get(route('quotes.index', ['page' => 2]))
        ->assertInertia(fn ($page) => $page->has('quotes.data', 5));
});

it('shows a quote with its edit history', function (): void {
    $quote = savedQuote($this->guild, 'Martin', 'hi');
    QuoteRevision::query()->create(['quote_id' => $quote->id, 'editor_discord_user_id' => $this->user->discord_user_id, 'snapshot' => [
        'lines' => [['speaker' => 'Martin', 'person_id' => null, 'text' => 'hi']], 'context' => null, 'said_at' => null,
    ]]);

    $this->actingAs($this->user)->get(route('quotes.show', $quote))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('quotes/Show')
            ->where('quote.id', $quote->id)
            ->has('revisions', 1)
            ->where('revisions.0.lines.0.speaker', 'Martin'));
});

it('hides quotes of other servers and unpublished quotes', function (): void {
    $other = savedQuote(Guild::factory()->create(), 'Kim', 'secret');
    $draft = Quote::factory()->for($this->guild)->draft()->create();

    $this->actingAs($this->user)->get(route('quotes.show', $other))->assertForbidden();
    $this->actingAs($this->user)->get(route('quotes.show', $draft))->assertForbidden();
});

it('redirects to a random visible quote', function (): void {
    $this->actingAs($this->user)->get(route('quotes.random'))->assertRedirect(route('quotes.index'));

    $quote = savedQuote($this->guild, 'Martin', 'hi');

    $this->actingAs($this->user)->get(route('quotes.random'))->assertRedirect(route('quotes.show', $quote));
});
