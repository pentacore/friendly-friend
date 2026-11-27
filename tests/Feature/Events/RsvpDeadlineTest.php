<?php

declare(strict_types=1);

use App\Discord\DiscordClient;
use App\Discord\Interactions\EventCustomId;
use App\Discord\Testing\FakeDiscordClient;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Guild;
use App\Models\User;
use App\Planning\Discord\EventAnnouncement;
use App\Planning\Discord\EventInteractions;
use App\Planning\Jobs\SendEventReminders;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DiscordPayloads as P;

beforeEach(function (): void {
    Date::setTestNow('2026-11-27T12:00:00Z');
    $this->guild = Guild::factory()->create(['discord_id' => '555', 'events_channel_id' => '888']);
    $this->host = User::factory()->memberOf($this->guild)->create();
    $this->guest = User::factory()->memberOf($this->guild)->create();
    $this->event = Event::factory()->create([
        'guild_id' => $this->guild->id,
        'created_by_discord_user_id' => $this->host->discord_user_id,
        'starts_at' => '2026-12-05T17:00:00Z',
        'rsvp_by' => '2026-12-01T17:00:00Z',
    ]);
});

it('saves the answer-by time in the event\'s timezone, not after the start', function (): void {
    $payload = ['title' => 'Crayfish party', 'starts_at' => '2026-12-05T18:00', 'timezone' => 'Europe/Stockholm'];

    $this->actingAs($this->host)->put(route('events.update', $this->event), [...$payload, 'rsvp_by' => '2026-12-06T12:00'])
        ->assertSessionHasErrors('rsvp_by');
    $this->actingAs($this->host)->put(route('events.update', $this->event), [...$payload, 'rsvp_by' => '2026-12-01T18:00'])
        ->assertSessionHasNoErrors();

    expect($this->event->fresh()?->rsvp_by?->toIso8601String())->toBe('2026-12-01T17:00:00+00:00');
});

it('takes any answer before the deadline', function (): void {
    $this->actingAs($this->guest)->put(route('events.rsvp', $this->event), ['status' => 'going'])->assertSessionHasNoErrors();
});

it('only takes Can\'t after the deadline, except from organisers', function (): void {
    Date::setTestNow('2026-12-02T12:00:00Z');

    $this->actingAs($this->guest)->put(route('events.rsvp', $this->event), ['status' => 'going'])->assertSessionHasErrors('status');
    $this->actingAs($this->guest)->put(route('events.rsvp', $this->event), ['status' => 'declined'])->assertSessionHasNoErrors();
    $this->actingAs($this->host)->put(route('events.rsvp', $this->event), ['status' => 'maybe'])->assertSessionHasNoErrors();

    $this->actingAs($this->guest)->get(route('events.show', $this->event))
        ->assertInertia(fn (Assert $page): Assert => $page->where('event.rsvp_closed', true));
});

it('closes Going and Maybe in Discord after the deadline', function (): void {
    app()->instance(DiscordClient::class, $discord = new FakeDiscordClient);
    Date::setTestNow('2026-12-02T12:00:00Z');

    $buttons = EventAnnouncement::message($this->event->load('rsvps'))['components'][0]['components'];
    expect(array_column($buttons, 'disabled'))->toBe([true, true, false]);

    app(EventInteractions::class)->handle([
        'id' => 'i', 'token' => 't', 'type' => 3, 'guild_id' => '555',
        'member' => P::member($this->guest->discordUser->discord_id),
        'data' => ['custom_id' => EventCustomId::rsvp($this->event->id, RsvpStatus::Going), 'component_type' => 2],
    ]);

    expect(FakeDiscordClient::content($discord->lastResponse()))->toContain('closed')
        ->and(EventRsvp::query()->count())->toBe(0);
});

it('nudges people on maybe a day before the deadline, once', function (): void {
    config()->set('discord.bot_token', 'token');
    app()->instance(DiscordClient::class, $discord = new FakeDiscordClient);
    EventRsvp::query()->create(['event_id' => $this->event->id, 'discord_user_id' => $this->guest->discord_user_id, 'status' => 'maybe']);
    EventRsvp::query()->create(['event_id' => $this->event->id, 'discord_user_id' => $this->host->discord_user_id, 'status' => 'going']);
    Date::setTestNow('2026-11-30T18:00:00Z');

    dispatch_sync(new SendEventReminders);
    dispatch_sync(new SendEventReminders);

    expect($discord->directMessages())->toHaveCount(1)
        ->and($discord->directMessages()[0]['user_id'])->toBe($this->guest->discordUser->discord_id)
        ->and(FakeDiscordClient::content($discord->directMessages()[0]['message']))->toContain('maybe');
});

it('refreshes the announcement once when answers close', function (): void {
    config()->set('discord.bot_token', 'token');
    app()->instance(DiscordClient::class, $discord = new FakeDiscordClient);
    $this->event->forceFill(['discord_message_id' => '900', 'announce_channel_id' => '888'])->save();
    Date::setTestNow('2026-12-01T17:03:00Z');

    dispatch_sync(new SendEventReminders);
    dispatch_sync(new SendEventReminders);

    expect($discord->editedMessages())->toHaveCount(1)
        ->and($this->event->fresh()?->rsvp_closed_synced_at)->not->toBeNull();
});
