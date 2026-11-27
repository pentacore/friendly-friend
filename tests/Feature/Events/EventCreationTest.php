<?php

declare(strict_types=1);

use App\Enums\Currency;
use App\Enums\QuestionKind;
use App\Enums\RideDirection;
use App\Enums\SplitMode;
use App\Models\Event;
use App\Models\EventExpense;
use App\Models\EventRsvp;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Date::setTestNow('2026-11-27T12:00:00Z');
    $this->guild = Guild::factory()->create();
    $this->host = User::factory()->memberOf($this->guild)->create();
});

function basics(Guild $guild, array $overrides = []): array
{
    return ['guild_id' => $guild->id, 'title' => 'Crayfish party', 'starts_at' => '2026-12-05T18:00', 'timezone' => 'Europe/Stockholm', ...$overrides];
}

it('refuses times in the past', function (string $field, array $overrides): void {
    $this->actingAs($this->host)->post(route('events.store'), basics($this->guild, $overrides))->assertSessionHasErrors($field);
})->with([
    'start' => ['starts_at', ['starts_at' => '2026-11-27T12:30']],
    'answer by' => ['rsvp_by', ['rsvp_by' => '2026-11-20T12:00']],
    'proposed date' => ['date_options.1.starts_at', ['starts_at' => null, 'date_options' => [['starts_at' => '2026-12-05T18:00'], ['starts_at' => '2026-11-01T18:00']]]],
]);

it('lets a past event be edited without moving it, but not moved into the past', function (): void {
    $event = Event::factory()->create(['guild_id' => $this->guild->id, 'created_by_discord_user_id' => $this->host->discord_user_id, 'starts_at' => '2026-11-01T17:00:00Z', 'ends_at' => null, 'timezone' => 'Europe/Stockholm']);

    $this->actingAs($this->host)->put(route('events.update', $event), ['title' => 'Typo fixed', 'starts_at' => '2026-11-01T18:00', 'timezone' => 'Europe/Stockholm'])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->host)->put(route('events.update', $event), ['title' => 'Typo fixed', 'starts_at' => '2026-11-02T18:00', 'timezone' => 'Europe/Stockholm'])
        ->assertSessionHasErrors('starts_at');
});

it('refuses proposing a past date to a date vote', function (): void {
    $event = Event::factory()->create(['guild_id' => $this->guild->id, 'created_by_discord_user_id' => $this->host->discord_user_id, 'date_polling' => true]);

    $this->actingAs($this->host)->post(route('events.dates.store', $event), ['starts_at' => '2026-11-26T18:00'])->assertSessionHasErrors('starts_at');
});

it('plans questions, food, a ride and costs right away', function (): void {
    $this->actingAs($this->host)->post(route('events.store'), basics($this->guild, [
        'questions' => [['prompt' => 'Will you eat crayfish?', 'kind' => 'yes_no'], ['prompt' => 'Sauna slot?', 'kind' => 'choice', 'options' => ['16:00', '18:00', '']]],
        'bring' => [['label' => 'Chairs', 'quantity' => 6]],
        'shopping' => [['label' => 'Crisps', 'quantity' => '3 bags']],
        'meals' => [['date' => '2026-12-05', 'slot' => 'dinner', 'title' => 'Crayfish']],
        'ride' => ['direction' => 'to', 'seats' => 3, 'departs_from' => 'Central station', 'departs_at' => '2026-12-05T16:00'],
        'costs' => [['description' => 'Cabin rent', 'amount' => '4000']],
    ]))->assertSessionHasNoErrors();

    $event = Event::query()->sole();
    expect($event->questions()->pluck('kind')->all())->toBe([QuestionKind::YesNo, QuestionKind::Choice])
        ->and($event->questions()->get()[1]?->options)->toBe(['16:00', '18:00'])
        ->and($event->bringItems()->sole()->quantity_needed)->toBe(6)
        ->and($event->shoppingItems()->sole()->added_by_discord_user_id)->toBe($this->host->discord_user_id)
        ->and($event->meals()->sole()->title)->toBe('Crayfish')
        ->and($event->rides()->sole())->direction->toBe(RideDirection::To)->seats->toBe(3)
        ->and($event->rides()->sole()->departs_at?->toIso8601String())->toBe('2026-12-05T15:00:00+00:00')
        ->and($event->expenses()->sole())->split_mode->toBe(SplitMode::Attendees)->amount->toBe(400000);
});

it('needs two options for a pick-one question', function (): void {
    $this->actingAs($this->host)->post(route('events.store'), basics($this->guild, [
        'questions' => [['prompt' => 'Sauna slot?', 'kind' => 'choice', 'options' => ['16:00']]],
    ]))->assertSessionHasErrors('questions.0.options');
});

it('splits a cost among everyone going as they answer, guests included', function (): void {
    $this->actingAs($this->host)->post(route('events.store'), basics($this->guild, ['costs' => [['description' => 'Cabin rent', 'amount' => '400']]]));
    $event = Event::query()->sole();

    $this->actingAs($this->host)->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page): Assert => $page->where('costs.expenses.0.shares', [['member' => $this->host->discordUser->toMember(), 'amount' => 40000]])->where('costs.settle_up', []));

    $bo = User::factory()->memberOf($this->guild)->create();
    EventRsvp::query()->create(['event_id' => $event->id, 'discord_user_id' => $bo->discord_user_id, 'status' => 'going', 'guests' => 2]);

    $this->actingAs($this->host)->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('costs.settle_up', [['from' => $bo->discordUser->toMember(), 'to' => $this->host->discordUser->toMember(), 'amount' => 30000]]));
});

it('takes "everyone going" on the Costs tab without picking people', function (): void {
    $event = Event::factory()->create(['guild_id' => $this->guild->id, 'created_by_discord_user_id' => $this->host->discord_user_id]);

    $this->actingAs($this->host)->post(route('events.costs.expenses.store', $event), [
        'description' => 'Firewood', 'amount' => '200', 'paid_by' => $this->host->discord_user_id, 'split_mode' => 'attendees',
    ])->assertSessionHasNoErrors();

    expect(EventExpense::query()->sole()->shares()->count())->toBe(0);
});

it('plans an event in another currency, and changes it only while there are no costs', function (): void {
    $this->actingAs($this->host)->post(route('events.store'), basics($this->guild, ['currency' => 'EUR', 'costs' => [['description' => 'Cabin', 'amount' => '120.50']]]))
        ->assertSessionHasNoErrors();
    $event = Event::query()->sole();
    expect($event->currency)->toBe(Currency::Eur)
        ->and(EventExpense::query()->sole()->amount)->toBe(12050);

    $edit = ['title' => 'Crayfish party', 'starts_at' => '2026-12-05T18:00', 'timezone' => 'Europe/Stockholm'];
    $this->actingAs($this->host)->put(route('events.update', $event), [...$edit, 'currency' => 'NOK'])->assertSessionHasErrors('currency');
    $this->actingAs($this->host)->put(route('events.update', $event), [...$edit, 'currency' => 'EUR'])->assertSessionHasNoErrors();
    $this->actingAs($this->host)->put(route('events.update', $event), $edit)->assertSessionHasNoErrors();
    expect($event->refresh()->currency)->toBe(Currency::Eur);

    EventExpense::query()->delete();
    $this->actingAs($this->host)->put(route('events.update', $event), [...$edit, 'currency' => 'NOK'])->assertSessionHasNoErrors();
    $this->actingAs($this->host)->put(route('events.update', $event), [...$edit, 'currency' => 'XYZ'])->assertSessionHasErrors('currency');
    expect($event->refresh()->currency)->toBe(Currency::Nok);

    $this->actingAs($this->host)->get(route('events.edit', $event))
        ->assertInertia(fn (Assert $page): Assert => $page->where('form.currency', 'NOK')->where('currency_locked', false));
});

it('plans in kronor by default', function (): void {
    $this->actingAs($this->host)->post(route('events.store'), basics($this->guild))->assertSessionHasNoErrors();

    expect(Event::query()->sole()->currency)->toBe(Currency::Sek);
});
