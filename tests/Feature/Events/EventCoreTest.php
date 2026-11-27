<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Enums\RsvpStatus;
use App\Events\EventChanged;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Guild;
use App\Models\GuildMember;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event as EventFacade;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Date::setTestNow('2026-11-27T12:00:00Z');
    $this->guild = Guild::factory()->create(['name' => 'Friends']);
    $this->user = User::factory()->memberOf($this->guild)->create();
    $this->friend = User::factory()->memberOf($this->guild)->create();
});

function eventIn(Guild $guild, array $attributes = []): Event
{
    return Event::factory()->create(['guild_id' => $guild->id, ...$attributes]);
}

it('creates an event in local time, with the creator going and co-hosts from the server', function (): void {
    $outsider = DiscordUser::factory()->create();

    $this->actingAs($this->user)->post(route('events.store'), [
        'guild_id' => $this->guild->id,
        'title' => 'Crayfish party',
        'starts_at' => '2026-12-05T18:00',
        'ends_at' => '2026-12-05T23:30',
        'timezone' => 'Europe/Stockholm',
        'host_ids' => [$this->friend->discord_user_id, $outsider->id],
    ])->assertRedirect();

    $event = Event::query()->sole();
    expect($event->starts_at->toIso8601String())->toBe('2026-12-05T17:00:00+00:00')
        ->and($event->hosts->modelKeys())->toBe([$this->friend->discord_user_id])
        ->and($event->rsvps()->sole()->status)->toBe(RsvpStatus::Going);
});

it('only creates events in servers you can see', function (): void {
    $other = Guild::factory()->create();

    $this->actingAs($this->user)->post(route('events.store'), [
        'guild_id' => $other->id, 'title' => 'Nope', 'starts_at' => '2026-12-05T18:00', 'timezone' => 'Europe/Stockholm',
    ])->assertSessionHasErrors('guild_id');
});

it('rejects an end before the start', function (): void {
    $this->actingAs($this->user)->post(route('events.store'), [
        'guild_id' => $this->guild->id, 'title' => 'Backwards', 'starts_at' => '2026-12-05T18:00', 'ends_at' => '2026-12-05T17:00', 'timezone' => 'Europe/Stockholm',
    ])->assertSessionHasErrors('ends_at');
});

it('lists upcoming and past events of your servers', function (): void {
    eventIn($this->guild, ['title' => 'Soon']);
    eventIn($this->guild, ['title' => 'Ages ago', 'starts_at' => now()->subMonth(), 'ends_at' => null]);
    eventIn(Guild::factory()->create(), ['title' => 'Secret']);

    $this->actingAs($this->user)->get(route('events.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->component('events/Index')
            ->where('upcoming.0.title', 'Soon')->has('upcoming', 1)
            ->where('past.0.title', 'Ages ago')->has('past', 1));
});

it('hides events of servers you cannot see', function (): void {
    $this->actingAs($this->user)->get(route('events.show', eventIn(Guild::factory()->create())))->assertForbidden();
});

it('lets the creator, co-hosts and admins edit, and nobody else', function (): void {
    $event = eventIn($this->guild, ['created_by_discord_user_id' => $this->user->discord_user_id]);
    $host = User::factory()->memberOf($this->guild)->create();
    $event->hosts()->attach($host->discord_user_id);
    $admin = User::factory()->memberOf($this->guild, ['900'])->create();

    foreach ([$this->user, $host, $admin] as $editor) {
        $this->actingAs($editor)->get(route('events.edit', $event))->assertOk();
    }

    $this->actingAs($this->friend)->get(route('events.edit', $event))->assertForbidden();
    $this->actingAs($this->friend)->put(route('events.update', $event), ['title' => 'Mine now', 'starts_at' => '2026-12-05T18:00', 'timezone' => 'UTC'])->assertForbidden();
});

it('shows the edit form in the event\'s timezone', function (): void {
    $event = eventIn($this->guild, ['created_by_discord_user_id' => $this->user->discord_user_id, 'starts_at' => '2026-12-05T17:00:00Z', 'ends_at' => null]);

    $this->actingAs($this->user)->get(route('events.edit', $event))
        ->assertInertia(fn (Assert $page): Assert => $page->where('form.starts_at', '2026-12-05T18:00')->where('form.ends_at', ''));
});

it('records RSVPs and broadcasts the change', function (): void {
    EventFacade::fake([EventChanged::class]);
    $event = eventIn($this->guild);

    $this->actingAs($this->friend)->put(route('events.rsvp', $event), ['status' => 'going', 'guests' => 2, 'needs_ride' => true])->assertRedirect();
    $this->actingAs($this->friend)->put(route('events.rsvp', $event), ['status' => 'declined', 'guests' => 2, 'needs_ride' => true]);

    $rsvp = EventRsvp::query()->sole();
    expect([$rsvp->status, $rsvp->guests, $rsvp->needs_ride])->toBe([RsvpStatus::Declined, 0, false]);
    EventFacade::assertDispatched(EventChanged::class, fn (EventChanged $changed): bool => $changed->eventId === $event->id && $changed->section === 'overview');
});

it('shows counts and your own answer', function (): void {
    $event = eventIn($this->guild);
    EventRsvp::query()->create(['event_id' => $event->id, 'discord_user_id' => $this->friend->discord_user_id, 'status' => 'going', 'guests' => 1]);
    EventRsvp::query()->create(['event_id' => $event->id, 'discord_user_id' => $this->user->discord_user_id, 'status' => 'maybe']);

    $this->actingAs($this->user)->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('event.counts', ['going' => 1, 'maybe' => 1, 'declined' => 0, 'guests' => 1])
            ->where('event.my_rsvp.status', 'maybe')
            ->where('event.can', ['edit' => false, 'participate' => true])
            ->where('attendees.0.status', 'going'));
});

it('stops RSVPs once cancelled, and can be called back on', function (): void {
    $event = eventIn($this->guild, ['created_by_discord_user_id' => $this->user->discord_user_id]);

    $this->actingAs($this->user)->post(route('events.cancel', $event))->assertRedirect();
    expect($event->fresh()?->status)->toBe(EventStatus::Cancelled);
    $this->actingAs($this->friend)->put(route('events.rsvp', $event), ['status' => 'going'])->assertForbidden();

    $this->actingAs($this->user)->post(route('events.cancel', $event), ['cancelled' => false]);
    expect($event->fresh()?->status)->toBe(EventStatus::Planned);
});

it('authorizes the live channel by server access', function (): void {
    $mine = eventIn($this->guild);
    $theirs = eventIn(Guild::factory()->create());
    $channels = Broadcast::driver()->getChannels();
    $callback = $channels['events.{eventId}'];

    expect($callback($this->user, $mine->id))->toBeTrue()
        ->and($callback($this->user, $theirs->id))->toBeFalse();
});

it('does not let non-members be co-hosts', function (): void {
    $leftServer = User::factory()->memberOf($this->guild)->create();
    GuildMember::query()->where('discord_user_id', $leftServer->discord_user_id)->update(['in_guild' => false]);

    $this->actingAs($this->user)->post(route('events.store'), [
        'guild_id' => $this->guild->id, 'title' => 'x', 'starts_at' => '2026-12-05T18:00', 'timezone' => 'UTC', 'host_ids' => [$leftServer->discord_user_id],
    ]);

    expect(Event::query()->sole()->hosts)->toBeEmpty();
});
