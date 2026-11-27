<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Guild;
use App\Models\Person;
use App\Models\PersonAlias;
use App\Models\Quote;
use App\Models\User;

/**
 * The search box in the header (Ctrl+K): a few quotes, people and events
 * the user can see, matching what they typed.
 */
beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
    $this->user = User::factory()->memberOf($this->guild)->create();
    $this->hidden = Guild::factory()->create();
});

function searchableQuoteBy(Guild $guild, Person $person, string $text): Quote
{
    $quote = Quote::factory()->for($guild)->withLine($person->display_name, $text)->create();
    $quote->lines()->update(['person_id' => $person->id]);
    $quote->refreshSearchText();

    return $quote;
}

it('finds quotes, people and events the user can see', function (): void {
    $hakan = Person::createNamed('Håkan');
    PersonAlias::query()->create(['person_id' => $hakan->id, 'alias' => 'Hacke']);
    $quote = searchableQuoteBy($this->guild, $hakan, 'the crayfish were alive');
    searchableQuoteBy($this->hidden, Person::createNamed('Hidden Håkan'), 'crayfish elsewhere');
    Person::createNamed('Håkan Without Quotes');
    $event = Event::factory()->for($this->guild)->create(['title' => 'Crayfish party']);
    Event::factory()->for($this->hidden)->create(['title' => 'Secret crayfish party']);

    $this->actingAs($this->user)->getJson(route('search', ['q' => 'crayfish']))
        ->assertOk()
        ->assertJsonPath('quotes.0.id', $quote->id)
        ->assertJsonPath('quotes.0.speaker', 'Håkan')
        ->assertJsonPath('quotes.0.text', 'the crayfish were alive')
        ->assertJsonCount(1, 'quotes')
        ->assertJsonPath('events', [['id' => $event->id, 'title' => 'Crayfish party', 'starts_at' => $event->starts_at->toIso8601String()]])
        ->assertJsonPath('people', [])
        ->assertJsonPath('close_matches', false);

    $this->actingAs($this->user)->getJson(route('search', ['q' => 'hakan']))
        ->assertJsonPath('people', [['slug' => $hakan->slug, 'display_name' => 'Håkan']]);
    $this->actingAs($this->user)->getJson(route('search', ['q' => 'hack']))
        ->assertJsonPath('people.0.slug', $hakan->slug);
});

it('falls back to close quote matches and takes % literally', function (): void {
    searchableQuoteBy($this->guild, Person::createNamed('Bo'), 'one more kanelbulle');
    Event::factory()->for($this->guild)->create(['title' => '100% fun']);
    Event::factory()->for($this->guild)->create(['title' => '100 fun']);

    $this->actingAs($this->user)->getJson(route('search', ['q' => 'kanelbule']))
        ->assertJsonCount(1, 'quotes')
        ->assertJsonPath('close_matches', true);
    $this->actingAs($this->user)->getJson(route('search', ['q' => '100%']))
        ->assertJsonCount(1, 'events');
});

it('needs a search and a member', function (): void {
    $this->getJson(route('search', ['q' => 'x']))->assertUnauthorized();
    $this->actingAs($this->user)->getJson(route('search'))->assertUnprocessable();
});
