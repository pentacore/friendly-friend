<?php

declare(strict_types=1);

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;

beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
    $this->user = User::factory()->memberOf($this->guild)->create();
});

function quoteBy(Guild $guild, Person $person, array $attributes = []): Quote
{
    $quote = Quote::factory()->for($guild)->create($attributes);
    QuoteLine::factory()->for($quote)->create(['person_id' => $person->id, 'speaker_name' => $person->display_name]);

    return $quote;
}

it('ranks quotes, people and submitters', function (): void {
    $martin = Person::createNamed('Martin');
    $bo = Person::createNamed('Bo');
    $submitter = DiscordUser::factory()->create(['global_name' => 'Kim']);

    $best = quoteBy($this->guild, $martin, ['score' => 9, 'published_at' => now()->subMonths(3), 'submitted_by_discord_user_id' => $submitter->id]);
    $recent = quoteBy($this->guild, $martin, ['score' => 4, 'submitted_by_discord_user_id' => $submitter->id]);
    $ranked = quoteBy($this->guild, $bo, ['score' => 1, 'elo' => 1200, 'matchup_count' => 5]);
    quoteBy(Guild::factory()->create(), $bo, ['score' => 99]);

    $this->actingAs($this->user)->get(route('top'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('quotes/Top')
            ->where('all_time.0.id', $best->id)
            ->where('this_month.0.id', $recent->id)
            ->has('this_month', 2)
            ->where('elo.0.id', $ranked->id)
            ->has('elo', 1)
            ->where('most_quoted.0.display_name', 'Martin')
            ->where('most_quoted.0.quote_count', 2)
            ->where('top_submitters.0.name', 'Kim')
            ->where('top_submitters.0.quote_count', 2));
});
