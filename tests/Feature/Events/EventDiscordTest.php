<?php

declare(strict_types=1);

use App\Discord\DiscordClient;
use App\Discord\Gateway\GatewayEventRouter;
use App\Discord\Interactions\EventCustomId;
use App\Discord\Interactions\InteractionAck;
use App\Discord\Interactions\InteractionAcknowledger;
use App\Discord\Testing\FakeDiscordClient;
use App\Discord\Testing\FakeInteractionAcknowledger;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Guild;
use App\Models\User;
use App\Planning\Discord\EventInteractions;
use App\Planning\Jobs\HandleEventInteraction;
use App\Planning\Jobs\SendEventReminders;
use App\Planning\Jobs\SyncEventToDiscord;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DiscordPayloads as P;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Date::setTestNow('2026-11-27T12:00:00Z');
    config()->set('discord.bot_token', 'token');
    app()->instance(DiscordClient::class, $this->discord = new FakeDiscordClient);
    $this->guild = Guild::factory()->create(['discord_id' => '555', 'events_channel_id' => '888']);
    $this->host = User::factory()->memberOf($this->guild)->create();
});

function plannedEvent(Guild $guild, User $host, array $attributes = []): Event
{
    return Event::factory()->create(['guild_id' => $guild->id, 'created_by_discord_user_id' => $host->discord_user_id, 'starts_at' => '2026-12-05T17:00:00Z', ...$attributes]);
}

function rsvpButton(Event $event, RsvpStatus $status, string $userId = '42'): array
{
    return [
        'id' => 'int-9', 'token' => 'tok-9', 'type' => 3, 'guild_id' => '555', 'channel_id' => '888',
        'member' => P::member($userId),
        'data' => ['custom_id' => EventCustomId::rsvp($event->id, $status), 'component_type' => 2],
    ];
}

it('announces a new event with RSVP buttons and mirrors it as a Discord event', function (): void {
    $this->actingAs($this->host)->post(route('events.store'), [
        'guild_id' => $this->guild->id, 'title' => 'Crayfish party', 'location' => 'The cabin',
        'starts_at' => '2026-12-05T18:00', 'timezone' => 'Europe/Stockholm',
    ]);

    $event = Event::query()->sole();
    $sent = $this->discord->sentMessages()[0];
    $buttons = $sent['message']['components'][0]['components'];

    expect($sent['channel_id'])->toBe('888')
        ->and($sent['message']['embeds'][0]['title'])->toBe('Crayfish party')
        ->and(array_column($buttons, 'custom_id'))->toBe([EventCustomId::rsvp($event->id, RsvpStatus::Going), EventCustomId::rsvp($event->id, RsvpStatus::Maybe), EventCustomId::rsvp($event->id, RsvpStatus::Declined)])
        ->and($event->discord_message_id)->not->toBeNull()
        ->and($event->discord_scheduled_event_id)->not->toBeNull()
        ->and($this->discord->scheduledEvents()[$event->discord_scheduled_event_id])->toMatchArray([
            'guild_id' => '555', 'name' => 'Crayfish party', 'entity_type' => 3,
            'scheduled_start_time' => '2026-12-05T17:00:00+00:00', 'scheduled_end_time' => '2026-12-05T23:00:00+00:00',
            'entity_metadata' => ['location' => 'The cabin'],
        ]);
});

it('edits the announcement when counts change and cancels the Discord event', function (): void {
    $event = plannedEvent($this->guild, $this->host);
    dispatch_sync(new SyncEventToDiscord($event->id));
    $event->refresh();

    $this->actingAs($this->host)->put(route('events.rsvp', $event), ['status' => 'maybe']);
    $this->actingAs($this->host)->post(route('events.cancel', $event));

    $edits = $this->discord->editedMessages();
    expect(end($edits)['message']['embeds'][0]['title'])->toStartWith('Cancelled: ')
        ->and(end($edits)['message']['components'][0]['components'][0]['disabled'])->toBeTrue()
        ->and(collect($edits[0]['message']['embeds'][0]['fields'])->firstWhere('name', 'Coming')['value'])->toContain('1 maybe')
        ->and($this->discord->scheduledEvents()[$event->discord_scheduled_event_id]['status'])->toBe(4);
});

it('does nothing on Discord without an events channel or for disabled servers', function (): void {
    $this->guild->update(['events_channel_id' => null]);
    $event = plannedEvent($this->guild, $this->host);
    dispatch_sync(new SyncEventToDiscord($event->id));

    $this->guild->update(['events_channel_id' => '888', 'enabled' => false]);
    dispatch_sync(new SyncEventToDiscord($event->id));

    expect($this->discord->sentMessages())->toBe([]);
});

it('still announces when the bot may not create Discord events', function (): void {
    $this->discord->failScheduledEventsWith(new RequestException(new Response(new Psr7Response(403, [], '{"code":50013}'))));
    $event = plannedEvent($this->guild, $this->host);

    dispatch_sync(new SyncEventToDiscord($event->id));

    expect($this->discord->sentMessages())->toHaveCount(1)
        ->and($event->fresh()?->discord_scheduled_event_id)->toBeNull();
});

it('acknowledges RSVP buttons privately and queues them', function (): void {
    Queue::fake();
    app()->instance(InteractionAcknowledger::class, $acks = new FakeInteractionAcknowledger);
    $event = plannedEvent($this->guild, $this->host);

    expect(InteractionAck::body(rsvpButton($event, RsvpStatus::Going)))->toBe(['type' => 5, 'data' => ['flags' => 64]]);
    app(GatewayEventRouter::class)->route('INTERACTION_CREATE', rsvpButton($event, RsvpStatus::Going));

    Queue::assertPushed(HandleEventInteraction::class);
    expect($acks->acknowledged)->toHaveCount(1);
});

it('records an RSVP from the button, keeping what was set on the website', function (): void {
    $event = plannedEvent($this->guild, $this->host);
    EventRsvp::query()->create(['event_id' => $event->id, 'discord_user_id' => $this->host->discord_user_id, 'status' => 'maybe', 'guests' => 2, 'note' => 'late']);

    app(EventInteractions::class)->handle(rsvpButton($event, RsvpStatus::Going, $this->host->discordUser->discord_id));

    $rsvp = EventRsvp::query()->sole();
    expect([$rsvp->status, $rsvp->guests, $rsvp->note])->toBe([RsvpStatus::Going, 2, 'late'])
        ->and(FakeDiscordClient::content($this->discord->lastResponse()))->toContain("You're going");
});

it('refuses RSVPs from members without access, and on cancelled events', function (): void {
    $event = plannedEvent($this->guild, $this->host);

    app(EventInteractions::class)->handle([...rsvpButton($event, RsvpStatus::Going, '777'), 'member' => P::member('777', roles: [])]);
    expect(FakeDiscordClient::content($this->discord->lastResponse()))->toContain("don't have access");

    $event->update(['status' => 'cancelled']);
    app(EventInteractions::class)->handle(rsvpButton($event, RsvpStatus::Going, $this->host->discordUser->discord_id));
    expect(FakeDiscordClient::content($this->discord->lastResponse()))->toContain('was cancelled')
        ->and(EventRsvp::query()->count())->toBe(0);
});

it('reminds people going a day and two hours before, once each', function (): void {
    $event = plannedEvent($this->guild, $this->host, ['starts_at' => '2026-11-28T10:00:00Z']);
    $closed = User::factory()->memberOf($this->guild)->create();
    $this->discord->withClosedDms($closed->discordUser->discord_id);
    foreach ([$this->host, $closed] as $user) {
        EventRsvp::query()->create(['event_id' => $event->id, 'discord_user_id' => $user->discord_user_id, 'status' => 'going']);
    }
    $maybe = User::factory()->memberOf($this->guild)->create();
    EventRsvp::query()->create(['event_id' => $event->id, 'discord_user_id' => $maybe->discord_user_id, 'status' => 'maybe']);

    dispatch_sync(new SendEventReminders);
    dispatch_sync(new SendEventReminders);

    expect($this->discord->directMessages())->toHaveCount(1)
        ->and($this->discord->directMessages()[0]['user_id'])->toBe($this->host->discordUser->discord_id)
        ->and($this->discord->sentMessages()[0]['message']['allowed_mentions'])->toBe(['users' => [$closed->discordUser->discord_id]]);

    Date::setTestNow('2026-11-28T08:30:00Z');
    dispatch_sync(new SendEventReminders);

    expect($this->discord->directMessages())->toHaveCount(2)
        ->and($event->fresh()?->reminded_hours_at)->not->toBeNull();
});

it('sends only the last reminder for an event planned at short notice', function (): void {
    $event = plannedEvent($this->guild, $this->host, ['starts_at' => '2026-11-27T13:00:00Z']);
    EventRsvp::query()->create(['event_id' => $event->id, 'discord_user_id' => $this->host->discord_user_id, 'status' => 'going']);

    dispatch_sync(new SendEventReminders);

    expect($this->discord->directMessages())->toHaveCount(1)
        ->and($event->fresh()?->reminded_day_at)->not->toBeNull();
});
