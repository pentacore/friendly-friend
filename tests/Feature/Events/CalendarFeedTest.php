<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Guild;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A personal calendar feed: a secret URL calendar apps subscribe to, with
 * the events you're going to (or maybe), host or created.
 */
beforeEach(function (): void {
    Date::setTestNow('2026-11-27T12:00:00Z');
    $this->guild = Guild::factory()->create();
    $this->user = User::factory()->memberOf($this->guild)->create();
});

function rsvp(Event $event, User $user, string $status): void
{
    EventRsvp::query()->create(['event_id' => $event->id, 'discord_user_id' => $user->discord_user_id, 'status' => $status]);
}

it('creates, shows, resets and turns off the feed link in settings', function (): void {
    $this->actingAs($this->user)->get(route('calendar-settings.edit'))
        ->assertInertia(fn (Assert $page): Assert => $page->component('settings/Calendar')->where('feed_url', null));

    $this->actingAs($this->user)->post(route('calendar-settings.store'))->assertRedirect(route('calendar-settings.edit'));
    $first = $this->user->refresh()->calendar_token;
    expect($first)->toBeString()->toHaveLength(40);

    $this->actingAs($this->user)->get(route('calendar-settings.edit'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('feed_url', route('calendar.feed', $first)));

    $this->actingAs($this->user)->post(route('calendar-settings.store'));
    expect($this->user->refresh()->calendar_token)->not->toBe($first);
    $this->get(route('calendar.feed', $first))->assertNotFound();

    $this->actingAs($this->user)->delete(route('calendar-settings.destroy'));
    expect($this->user->refresh()->calendar_token)->toBeNull();
});

it('feeds your events without logging in', function (): void {
    $this->user->forceFill(['calendar_token' => str_repeat('a', 40)])->save();
    $going = Event::factory()->create(['guild_id' => $this->guild->id, 'title' => 'Going one']);
    rsvp($going, $this->user, 'going');
    $declined = Event::factory()->create(['guild_id' => $this->guild->id, 'title' => 'Declined one']);
    rsvp($declined, $this->user, 'declined');
    Event::factory()->create(['guild_id' => $this->guild->id, 'title' => 'Not answered']);
    Event::factory()->create(['guild_id' => $this->guild->id, 'title' => 'My own', 'created_by_discord_user_id' => $this->user->discord_user_id]);
    $old = Event::factory()->create(['guild_id' => $this->guild->id, 'title' => 'Long ago', 'starts_at' => now()->subYear(), 'ends_at' => null]);
    rsvp($old, $this->user, 'going');
    $hidden = Event::factory()->create(['guild_id' => Guild::factory()->create()->id, 'title' => 'Other server']);
    rsvp($hidden, $this->user, 'going');
    $polling = Event::factory()->create(['guild_id' => $this->guild->id, 'title' => 'Date undecided', 'date_polling' => true]);
    rsvp($polling, $this->user, 'maybe');

    $response = $this->get(route('calendar.feed', str_repeat('a', 40)))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    $ics = $response->getContent();

    expect($ics)->toContain('BEGIN:VCALENDAR')
        ->toContain('SUMMARY:Going one')
        ->toContain('SUMMARY:My own')
        ->toContain('X-WR-CALNAME:Friendly Friend')
        ->not->toContain('Declined one')
        ->not->toContain('Not answered')
        ->not->toContain('Long ago')
        ->not->toContain('Other server')
        ->not->toContain('Date undecided')
        ->and(substr_count((string) $ics, 'BEGIN:VEVENT'))->toBe(2);

    $this->get(route('calendar.feed', str_repeat('b', 40)))->assertNotFound();
});
