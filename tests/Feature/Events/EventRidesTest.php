<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\EventRide;
use App\Models\EventRsvp;
use App\Models\Guild;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
    $this->driver = User::factory()->memberOf($this->guild)->create();
    $this->rider = User::factory()->memberOf($this->guild)->create();
    $this->event = Event::factory()->create(['guild_id' => $this->guild->id, 'timezone' => 'Europe/Stockholm']);
});

function offerRide(User $driver, Event $event, int $seats = 1, string $direction = 'to'): EventRide
{
    test()->actingAs($driver)->post(route('events.rides.store', $event), [
        'direction' => $direction, 'seats' => $seats, 'departs_from' => 'Central station', 'departs_at' => '2026-12-05T16:30',
    ])->assertRedirect();

    return EventRide::query()->latest('id')->firstOrFail();
}

it('offers a ride with a local departure time', function (): void {
    $ride = offerRide($this->driver, $this->event, 3);

    expect($ride->departs_at?->toIso8601String())->toBe('2026-12-05T15:30:00+00:00')
        ->and($ride->seats)->toBe(3)
        ->and($ride->driver_discord_user_id)->toBe($this->driver->discord_user_id);
});

it('fills seats and refuses when full or for the driver', function (): void {
    $ride = offerRide($this->driver, $this->event, 1);
    $late = User::factory()->memberOf($this->guild)->create();

    $this->actingAs($this->rider)->put(route('events.rides.join', [$this->event, $ride]), ['pickup_note' => 'By the bakery']);
    $this->actingAs($late)->put(route('events.rides.join', [$this->event, $ride]))->assertSessionHas('inertia.flash_data.toast.type', 'error');
    $this->actingAs($this->driver)->put(route('events.rides.join', [$this->event, $ride]));

    expect($ride->passengers()->pluck('discord_user_id')->all())->toBe([$this->rider->discord_user_id]);
});

it('lets you leave, and the driver remove you, but not strangers', function (): void {
    $ride = offerRide($this->driver, $this->event, 2);
    $other = User::factory()->memberOf($this->guild)->create();
    $this->actingAs($this->rider)->put(route('events.rides.join', [$this->event, $ride]));

    $this->actingAs($other)->delete(route('events.rides.leave', [$this->event, $ride]), ['discord_user_id' => $this->rider->discord_user_id])->assertForbidden();
    $this->actingAs($this->driver)->delete(route('events.rides.leave', [$this->event, $ride]), ['discord_user_id' => $this->rider->discord_user_id])->assertRedirect();

    expect($ride->passengers()->count())->toBe(0);
    $this->actingAs($other)->delete(route('events.rides.destroy', [$this->event, $ride]))->assertForbidden();
    $this->actingAs($this->driver)->delete(route('events.rides.destroy', [$this->event, $ride]))->assertRedirect();
    expect(EventRide::query()->count())->toBe(0);
});

it('lists who still needs a ride there', function (): void {
    EventRsvp::query()->create(['event_id' => $this->event->id, 'discord_user_id' => $this->rider->discord_user_id, 'status' => 'going', 'needs_ride' => true]);
    $stranded = User::factory()->memberOf($this->guild)->create();
    EventRsvp::query()->create(['event_id' => $this->event->id, 'discord_user_id' => $stranded->discord_user_id, 'status' => 'maybe', 'needs_ride' => true]);
    $ride = offerRide($this->driver, $this->event, 2, 'both');
    $this->actingAs($this->rider)->put(route('events.rides.join', [$this->event, $ride]));

    $this->actingAs($this->driver)->get(route('events.show', $this->event))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('transport.needs_ride', [$stranded->discordUser->toMember()])
            ->where('transport.rides.0.passengers.0.member.id', $this->rider->discord_user_id)
            ->where('transport.rides.0.can_manage', true));
});

it('keeps rides to their own event', function (): void {
    $other = Event::factory()->create(['guild_id' => $this->guild->id]);
    $ride = offerRide($this->driver, $other);

    $this->actingAs($this->rider)->put(route('events.rides.join', [$this->event, $ride]))->assertNotFound();
});

it('lets the driver edit a ride, but not below the seats already taken', function (): void {
    $ride = offerRide($this->driver, $this->event, 3);
    $this->actingAs($this->rider)->put(route('events.rides.join', [$this->event, $ride]));
    $second = User::factory()->memberOf($this->guild)->create();
    $this->actingAs($second)->put(route('events.rides.join', [$this->event, $ride]));

    $this->actingAs($this->rider)->put(route('events.rides.update', [$this->event, $ride]), ['direction' => 'to', 'seats' => 4])->assertForbidden();
    $this->actingAs($this->driver)->put(route('events.rides.update', [$this->event, $ride]), ['direction' => 'to', 'seats' => 1])
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');
    $this->actingAs($this->driver)->put(route('events.rides.update', [$this->event, $ride]), ['direction' => 'both', 'seats' => 2, 'departs_from' => 'The mall', 'departs_at' => '2026-12-05T17:00'])
        ->assertSessionHasNoErrors();

    expect($ride->fresh())->seats->toBe(2)->departs_from->toBe('The mall')
        ->and($ride->fresh()?->departs_at?->toIso8601String())->toBe('2026-12-05T16:00:00+00:00');
});
