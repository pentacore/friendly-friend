<?php

declare(strict_types=1);

use App\Models\Guild;
use App\Models\Person;
use App\Models\PersonAlias;
use App\Models\Quote;
use App\Models\User;

/**
 * The shared quote search (`Quote::search()`): prefixes while typing, every
 * word must match, `-word` excludes, and people are found by their current
 * name and nicknames, not only the name written on the quote.
 */
beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
});

function searchable(Guild $guild, string $speaker, string $text, ?Person $person = null, array $attributes = []): Quote
{
    $quote = Quote::factory()->for($guild)->withLine($speaker, $text)->create($attributes);
    $quote->refreshSearchText();

    if ($person instanceof Person) {
        $quote->lines()->update(['person_id' => $person->id]);
    }

    return $quote;
}

function found(string $text): array
{
    return Quote::query()->search($text)->orderBy('id')->pluck('id')->all();
}

it('matches words while they are still being typed', function (): void {
    $kanelbulle = searchable($this->guild, 'Bo', 'one more kanelbulle');
    searchable($this->guild, 'Bo', 'no buns today');

    expect(found('kanel'))->toBe([$kanelbulle->id])
        ->and(found('KANELBULLE'))->toBe([$kanelbulle->id])
        ->and(found('kanelbullar'))->toBe([]);
});

it('needs every word, and leaves out words starting with a minus', function (): void {
    $both = searchable($this->guild, 'Bo', 'coffee and cake');
    $coffee = searchable($this->guild, 'Bo', 'just coffee');

    expect(found('coffee cake'))->toBe([$both->id])
        ->and(found('coffee -cake'))->toBe([$coffee->id])
        ->and(found('  '))->toBe([$both->id, $coffee->id])
        ->and(found('!!! & |'))->toBe([$both->id, $coffee->id]);
});

it('finds quotes by the person\'s current name and nicknames', function (): void {
    $rolf = Person::createNamed('Uncle Rolf');
    PersonAlias::query()->create(['person_id' => $rolf->id, 'alias' => 'Rolle']);
    $quote = searchable($this->guild, 'Rolf', 'back in my day', $rolf);
    searchable($this->guild, 'Bo', 'my day was fine');

    expect(found('uncle'))->toBe([$quote->id])
        ->and(found('rolle day'))->toBe([$quote->id])
        ->and(found('rol'))->toBe([$quote->id]);
});

it('sorts the feed by best match when searching, or by newest or top score', function (): void {
    $user = User::factory()->memberOf($this->guild)->create();
    $once = searchable($this->guild, 'Bo', 'coffee is fine', attributes: ['published_at' => now(), 'score' => 1]);
    $thrice = searchable($this->guild, 'Kim', 'coffee coffee coffee', attributes: ['published_at' => now()->subDay(), 'score' => 5]);
    $ids = fn (array $query): array => collect($this->actingAs($user)->get(route('quotes.index', $query))
        ->viewData('page')['props']['quotes']['data'])->pluck('id')->all();

    expect($ids(['q' => 'coffee']))->toBe([$thrice->id, $once->id])
        ->and($ids(['q' => 'coffee', 'sort' => 'newest']))->toBe([$once->id, $thrice->id])
        ->and($ids(['sort' => 'top']))->toBe([$thrice->id, $once->id])
        ->and($ids([]))->toBe([$once->id, $thrice->id]);

    $this->actingAs($user)->get(route('quotes.index', ['sort' => 'random']))->assertSessionHasErrors('sort');
});

it('offers the people with visible quotes for the person picker', function (): void {
    $user = User::factory()->memberOf($this->guild)->create();
    searchable($this->guild, 'Bo', 'hi', Person::createNamed('Bo'));
    searchable(Guild::factory()->create(), 'Kim', 'hidden', Person::createNamed('Kim'));
    Person::createNamed('Nobody');

    $this->actingAs($user)->get(route('quotes.index'))
        ->assertInertia(fn ($page) => $page->where('people', [['slug' => 'bo', 'display_name' => 'Bo']]));
});

it('ignores accents, both ways', function (): void {
    $hakan = searchable($this->guild, 'Håkan', 'kör hårt');
    $person = searchable($this->guild, 'H', 'hej', Person::createNamed('Åsa Öberg'));

    expect(found('hakan kor'))->toBe([$hakan->id])
        ->and(found('HÅKAN'))->toBe([$hakan->id])
        ->and(found('asa oberg'))->toBe([$person->id]);
});

it('falls back to close matches only when nothing matches exactly', function (): void {
    $kanelbulle = searchable($this->guild, 'Bo', 'one more kanelbulle');
    $coffee = searchable($this->guild, 'Kim', 'just coffee');
    searchable($this->guild, 'Ann', 'toffee is better');
    $rolf = searchable($this->guild, 'Rolf', 'back in my day', Person::createNamed('Rolf Andersson'));

    $search = function (string $text): array {
        $query = Quote::query();
        $close = Quote::applySearch($query, $text);

        return [$close, $query->orderBy('id')->pluck('id')->all()];
    };

    expect($search('kanelbule'))->toBe([true, [$kanelbulle->id]])
        ->and($search('cofee'))->toBe([true, [$coffee->id]])
        ->and($search('coffee'))->toBe([false, [$coffee->id]])
        ->and($search('andersen'))->toBe([true, [$rolf->id]])
        ->and($search('kanelbule -more'))->toBe([true, []])
        ->and($search('xyzzyx'))->toBe([true, []]);
});

it('tells the feed when it shows close matches', function (): void {
    $user = User::factory()->memberOf($this->guild)->create();
    searchable($this->guild, 'Bo', 'one more kanelbulle');

    $this->actingAs($user)->get(route('quotes.index', ['q' => 'kanelbule']))
        ->assertInertia(fn ($page) => $page->where('close_matches', true)->has('quotes.data', 1));
    $this->actingAs($user)->get(route('quotes.index', ['q' => 'kanelbulle']))
        ->assertInertia(fn ($page) => $page->where('close_matches', false));
});
