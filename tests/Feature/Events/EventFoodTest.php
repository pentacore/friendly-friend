<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\EventBringItem;
use App\Models\EventMeal;
use App\Models\EventRsvp;
use App\Models\EventShoppingItem;
use App\Models\Guild;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
    $this->host = User::factory()->memberOf($this->guild)->create();
    $this->guest = User::factory()->memberOf($this->guild)->create();
    $this->event = Event::factory()->create(['guild_id' => $this->guild->id, 'created_by_discord_user_id' => $this->host->discord_user_id]);
});

it('lets organisers build the bring list and everyone claim from it', function (): void {
    $this->actingAs($this->host)->post(route('events.food.bring.store', $this->event), ['label' => 'Chairs', 'quantity_needed' => 6])->assertRedirect();
    $item = EventBringItem::query()->sole();

    $this->actingAs($this->guest)->put(route('events.food.bring.claim', [$this->event, $item]), ['quantity' => 4, 'note' => 'folding ones']);
    $this->actingAs($this->host)->put(route('events.food.bring.claim', [$this->event, $item]), ['quantity' => 1]);

    $this->actingAs($this->guest)->get(route('events.show', $this->event))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('food.bring.0.label', 'Chairs')
            ->where('food.bring.0.claimed', 5)
            ->has('food.bring.0.claims', 2));

    $this->actingAs($this->guest)->put(route('events.food.bring.claim', [$this->event, $item]), ['quantity' => 0]);
    expect($item->claims()->count())->toBe(1);
});

it('keeps the bring list and meal plan for organisers', function (): void {
    $this->actingAs($this->guest)->post(route('events.food.bring.store', $this->event), ['label' => 'Chairs', 'quantity_needed' => 6])->assertForbidden();
    $this->actingAs($this->guest)->post(route('events.food.meals.store', $this->event), ['date' => '2026-12-05', 'slot' => 'dinner', 'title' => 'Tacos'])->assertForbidden();
});

it('runs a shared shopping list', function (): void {
    $this->actingAs($this->guest)->post(route('events.food.shopping.store', $this->event), ['label' => 'Crisps', 'quantity' => '3 bags']);
    $item = EventShoppingItem::query()->sole();

    $this->actingAs($this->host)->put(route('events.food.shopping.tick', [$this->event, $item]), ['bought' => true]);
    expect($item->fresh()?->checked_by_discord_user_id)->toBe($this->host->discord_user_id);

    $other = User::factory()->memberOf($this->guild)->create();
    $this->actingAs($other)->delete(route('events.food.shopping.destroy', [$this->event, $item]))->assertForbidden();
    $this->actingAs($this->guest)->delete(route('events.food.shopping.destroy', [$this->event, $item]))->assertRedirect();
    expect(EventShoppingItem::query()->count())->toBe(0);
});

it('plans meals in order, with volunteer cooks', function (): void {
    $this->actingAs($this->host)->post(route('events.food.meals.store', $this->event), ['date' => '2026-12-06', 'slot' => 'breakfast', 'title' => 'Pancakes']);
    $this->actingAs($this->host)->post(route('events.food.meals.store', $this->event), ['date' => '2026-12-05', 'slot' => 'dinner', 'title' => 'Tacos']);
    $this->actingAs($this->host)->post(route('events.food.meals.store', $this->event), ['date' => '2026-12-05', 'slot' => 'lunch', 'title' => 'Soup']);
    $tacos = EventMeal::query()->where('title', 'Tacos')->sole();

    $this->actingAs($this->guest)->put(route('events.food.meals.cook', [$this->event, $tacos]), ['cooking' => true]);

    $this->actingAs($this->guest)->get(route('events.show', $this->event))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('food.meals.0.title', 'Soup')
            ->where('food.meals.1.title', 'Tacos')
            ->where('food.meals.1.cooks.0.id', $this->guest->discord_user_id)
            ->where('food.meals.2.title', 'Pancakes'));
});

it('shows dietary needs of who is coming to organisers only', function (): void {
    $this->guest->discordUser->forceFill(['dietary_notes' => 'No nuts'])->save();
    EventRsvp::query()->create(['event_id' => $this->event->id, 'discord_user_id' => $this->guest->discord_user_id, 'status' => 'going']);
    $declined = User::factory()->memberOf($this->guild)->create();
    $declined->discordUser->forceFill(['dietary_notes' => 'Vegan'])->save();
    EventRsvp::query()->create(['event_id' => $this->event->id, 'discord_user_id' => $declined->discord_user_id, 'status' => 'declined']);

    $this->actingAs($this->host)->get(route('events.show', $this->event))
        ->assertInertia(fn (Assert $page): Assert => $page->where('food.dietary', [['member' => $this->guest->discordUser->toMember(), 'notes' => 'No nuts']]));
    $this->actingAs($this->guest)->get(route('events.show', $this->event))
        ->assertInertia(fn (Assert $page): Assert => $page->where('food.dietary', null));
});

it('saves your dietary notes in settings', function (): void {
    $this->actingAs($this->guest)->put(route('food-settings.update'), ['dietary_notes' => '  No coriander '])->assertRedirect(route('food-settings.edit'));
    expect($this->guest->discordUser->fresh()?->dietary_notes)->toBe('No coriander');

    $this->actingAs($this->guest)->put(route('food-settings.update'), ['dietary_notes' => '']);
    expect($this->guest->discordUser->fresh()?->dietary_notes)->toBeNull();
});

it('refuses items of another event', function (): void {
    $other = Event::factory()->create(['guild_id' => $this->guild->id]);
    $item = EventBringItem::query()->create(['event_id' => $other->id, 'label' => 'x', 'quantity_needed' => 1]);

    $this->actingAs($this->guest)->put(route('events.food.bring.claim', [$this->event, $item]), ['quantity' => 1])->assertNotFound();
});
