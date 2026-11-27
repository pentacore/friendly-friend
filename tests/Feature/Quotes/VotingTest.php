<?php

declare(strict_types=1);

use App\Events\QuoteScoreChanged;
use App\Models\Guild;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
    $this->quote = Quote::factory()->for($this->guild)->withLine()->create();
    $this->user = User::factory()->memberOf($this->guild)->create();
});

function vote(User $user, Quote $quote, int $value)
{
    return test()->actingAs($user)->postJson(route('quotes.vote', $quote), ['value' => $value]);
}

it('records a vote and returns the new score', function (): void {
    Event::fake([QuoteScoreChanged::class]);

    vote($this->user, $this->quote, 1)
        ->assertOk()
        ->assertExactJson(['quote_id' => $this->quote->id, 'score' => 1, 'my_vote' => 1]);

    expect($this->quote->refresh()->score)->toBe(1);
    Event::assertDispatched(QuoteScoreChanged::class, fn (QuoteScoreChanged $event): bool => $event->quoteId === $this->quote->id
        && $event->score === 1
        && $event->broadcastOn()[0]->name === "private-guilds.{$this->guild->id}.quotes");
});

it('switches a vote and removes it when clicked again', function (): void {
    $other = User::factory()->memberOf($this->guild)->create();
    vote($other, $this->quote, 1);

    vote($this->user, $this->quote, 1)->assertJson(['score' => 2, 'my_vote' => 1]);
    vote($this->user, $this->quote, -1)->assertJson(['score' => 0, 'my_vote' => -1]);
    vote($this->user, $this->quote, -1)->assertJson(['score' => 1, 'my_vote' => 0]);
});

it('only accepts up or down', function (): void {
    vote($this->user, $this->quote, 5)->assertUnprocessable()->assertJsonValidationErrors('value');
});

it('only lets people who can see the quote vote', function (): void {
    $outsider = User::factory()->member()->create();

    vote($outsider, $this->quote, 1)->assertForbidden();
});

it('shows the user\'s vote in the feed', function (): void {
    vote($this->user, $this->quote, -1);

    $this->actingAs($this->user)->get(route('quotes.index'))
        ->assertInertia(fn ($page) => $page->where('quotes.data.0.my_vote', -1)->where('quotes.data.0.score', -1));
});

it('authorizes the live score channel per server', function (): void {
    // Tests use the null broadcaster (which allows everything), so call the
    // channel's authorization callback from routes/channels.php directly.
    $authorize = Broadcast::driver()->getChannels()->get('guilds.{guildId}.quotes');
    $outsider = User::factory()->member()->create();

    expect($authorize($this->user, $this->guild->id))->toBeTrue()
        ->and($authorize($outsider, $this->guild->id))->toBeFalse();
});
