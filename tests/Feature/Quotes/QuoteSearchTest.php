<?php

declare(strict_types=1);

use App\Models\Guild;
use App\Models\Person;
use App\Models\PersonAlias;
use App\Models\Quote;

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
