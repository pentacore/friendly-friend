<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\EventExpense;
use App\Models\EventPayment;
use App\Models\EventRsvp;
use App\Models\Guild;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
    [$this->anna, $this->bo, $this->cleo] = User::factory()->count(3)->memberOf($this->guild)->create()->all();
    $this->event = Event::factory()->create(['guild_id' => $this->guild->id, 'created_by_discord_user_id' => $this->anna->discord_user_id]);

    foreach ([$this->anna, $this->bo, $this->cleo] as $user) {
        EventRsvp::query()->create(['event_id' => $this->event->id, 'discord_user_id' => $user->discord_user_id, 'status' => 'going']);
    }
});

function ids(User ...$users): array
{
    return array_map(fn (User $user): int => $user->discord_user_id, $users);
}

it('splits an expense equally, without losing an öre', function (): void {
    $this->actingAs($this->anna)->post(route('events.costs.expenses.store', $this->event), [
        'description' => 'Crayfish', 'amount' => '100', 'paid_by' => $this->anna->discord_user_id,
        'split_mode' => 'equal', 'people' => ids($this->anna, $this->bo, $this->cleo),
    ])->assertSessionHasNoErrors();

    $shares = EventExpense::query()->sole()->shares()->orderBy('id')->pluck('amount')->all();
    expect($shares)->toBe([3334, 3333, 3333]);
});

it('takes exact amounts that must add up', function (): void {
    $payload = [
        'description' => 'Drinks', 'amount' => '120,50', 'paid_by' => $this->bo->discord_user_id, 'split_mode' => 'exact',
        'people' => ids($this->bo, $this->cleo),
    ];

    $this->actingAs($this->bo)->post(route('events.costs.expenses.store', $this->event), [...$payload, 'exact' => [$this->bo->discord_user_id => '20', $this->cleo->discord_user_id => '100']])
        ->assertSessionHasErrors('exact');

    $this->actingAs($this->bo)->post(route('events.costs.expenses.store', $this->event), [...$payload, 'exact' => [$this->bo->discord_user_id => '20.50', $this->cleo->discord_user_id => '100']])
        ->assertSessionHasNoErrors();

    expect(EventExpense::query()->sole()->amount)->toBe(12050);
});

it('only splits between people of the event', function (): void {
    $stranger = User::factory()->memberOf($this->guild)->create();

    $this->actingAs($this->anna)->post(route('events.costs.expenses.store', $this->event), [
        'description' => 'x', 'amount' => '10', 'paid_by' => $this->anna->discord_user_id,
        'split_mode' => 'equal', 'people' => ids($this->anna, $stranger),
    ])->assertSessionHasErrors('people.1');
});

it('computes balances and settles up, and payments count', function (): void {
    // Anna pays 300 for all three; Cleo pays 60 for Bo and Cleo.
    $this->actingAs($this->anna)->post(route('events.costs.expenses.store', $this->event), ['description' => 'Food', 'amount' => '300', 'paid_by' => $this->anna->discord_user_id, 'split_mode' => 'equal', 'people' => ids($this->anna, $this->bo, $this->cleo)]);
    $this->actingAs($this->cleo)->post(route('events.costs.expenses.store', $this->event), ['description' => 'Taxi', 'amount' => '60', 'paid_by' => $this->cleo->discord_user_id, 'split_mode' => 'equal', 'people' => ids($this->bo, $this->cleo)]);

    $this->actingAs($this->bo)->get(route('events.show', $this->event))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('costs.total', 36000)
            ->where('costs.balances.0.member.id', $this->anna->discord_user_id)
            ->where('costs.balances.0.balance', 20000)
            ->where('costs.settle_up', [
                ['from' => $this->bo->discordUser->toMember(), 'to' => $this->anna->discordUser->toMember(), 'amount' => 13000],
                ['from' => $this->cleo->discordUser->toMember(), 'to' => $this->anna->discordUser->toMember(), 'amount' => 7000],
            ]));

    $this->actingAs($this->bo)->post(route('events.costs.payments.store', $this->event), ['from' => $this->bo->discord_user_id, 'to' => $this->anna->discord_user_id, 'amount' => '130'])->assertSessionHasNoErrors();

    $this->actingAs($this->bo)->get(route('events.show', $this->event))
        ->assertInertia(fn (Assert $page): Assert => $page->has('costs.settle_up', 1)->where('costs.settle_up.0.amount', 7000));
});

it('keeps costs open on cancelled events', function (): void {
    $this->event->update(['status' => 'cancelled']);

    $this->actingAs($this->bo)->post(route('events.costs.expenses.store', $this->event), [
        'description' => 'Deposit', 'amount' => '500', 'paid_by' => $this->bo->discord_user_id, 'split_mode' => 'equal', 'people' => ids($this->anna, $this->bo),
    ])->assertSessionHasNoErrors();
});

it('lets the adder, the payer or organisers remove expenses and payments', function (): void {
    $this->actingAs($this->bo)->post(route('events.costs.expenses.store', $this->event), ['description' => 'Ice', 'amount' => '20', 'paid_by' => $this->bo->discord_user_id, 'split_mode' => 'equal', 'people' => ids($this->bo, $this->cleo)]);
    $this->actingAs($this->bo)->post(route('events.costs.payments.store', $this->event), ['from' => $this->cleo->discord_user_id, 'to' => $this->bo->discord_user_id, 'amount' => '10']);
    $expense = EventExpense::query()->sole();
    $payment = EventPayment::query()->sole();

    $this->actingAs($this->cleo)->delete(route('events.costs.expenses.destroy', [$this->event, $expense]))->assertForbidden();
    $this->actingAs($this->cleo)->delete(route('events.costs.payments.destroy', [$this->event, $payment]))->assertForbidden();
    $this->actingAs($this->anna)->delete(route('events.costs.payments.destroy', [$this->event, $payment]))->assertRedirect();
    $this->actingAs($this->bo)->delete(route('events.costs.expenses.destroy', [$this->event, $expense]))->assertRedirect();

    expect(EventExpense::query()->count() + EventPayment::query()->count())->toBe(0);
});

it('rejects zero, negative and nonsense amounts', function (string $amount): void {
    $this->actingAs($this->bo)->post(route('events.costs.payments.store', $this->event), ['from' => $this->bo->discord_user_id, 'to' => $this->anna->discord_user_id, 'amount' => $amount])
        ->assertSessionHasErrors('amount');
})->with(['0', '0.00', '-5', 'lots', '1.234']);

it('edits an expense, replacing how it is split', function (): void {
    $this->actingAs($this->anna)->post(route('events.costs.expenses.store', $this->event), ['description' => 'Food', 'amount' => '300', 'paid_by' => $this->anna->discord_user_id, 'split_mode' => 'equal', 'people' => ids($this->anna, $this->bo, $this->cleo)]);
    $expense = EventExpense::query()->sole();

    $this->actingAs($this->cleo)->put(route('events.costs.expenses.update', [$this->event, $expense]), ['description' => 'Mine', 'amount' => '1', 'paid_by' => $this->cleo->discord_user_id, 'split_mode' => 'equal', 'people' => ids($this->cleo)])
        ->assertForbidden();

    $this->actingAs($this->anna)->put(route('events.costs.expenses.update', [$this->event, $expense]), [
        'description' => 'Food and drinks', 'amount' => '200', 'paid_by' => $this->anna->discord_user_id, 'split_mode' => 'exact',
        'people' => ids($this->anna, $this->bo), 'exact' => [$this->anna->discord_user_id => '50', $this->bo->discord_user_id => '150'],
    ])->assertSessionHasNoErrors();

    expect($expense->fresh())->description->toBe('Food and drinks')->amount->toBe(20000)
        ->and($expense->shares()->orderBy('amount')->pluck('amount')->all())->toBe([5000, 15000]);
});
