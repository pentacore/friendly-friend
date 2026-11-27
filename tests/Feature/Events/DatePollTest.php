<?php

declare(strict_types=1);

use App\Discord\DiscordClient;
use App\Discord\Interactions\EventCustomId;
use App\Discord\Interactions\InteractionAck;
use App\Discord\Testing\FakeDiscordClient;
use App\Enums\DateVote;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\EventDateOption;
use App\Models\EventDateVote;
use App\Models\EventRsvp;
use App\Models\Guild;
use App\Models\User;
use App\Planning\Discord\EventInteractions;
use App\Planning\Jobs\SendEventReminders;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DiscordPayloads as P;

beforeEach(function (): void {
    Date::setTestNow('2026-11-27T12:00:00Z');
    config()->set('discord.bot_token', 'token');
    app()->instance(DiscordClient::class, $this->discord = new FakeDiscordClient);
    $this->guild = Guild::factory()->create(['discord_id' => '555', 'events_channel_id' => '888']);
    [$this->host, $this->anna, $this->bo] = User::factory()->count(3)->memberOf($this->guild)->create()->all();
});

function pollEvent(User $host, Guild $guild): Event
{
    test()->actingAs($host)->post(route('events.store'), [
        'guild_id' => $guild->id,
        'title' => 'Crayfish party',
        'timezone' => 'Europe/Stockholm',
        'date_options' => [
            ['starts_at' => '2026-12-12T18:00'],
            ['starts_at' => '2026-12-05T18:00', 'ends_at' => '2026-12-05T23:00'],
        ],
    ])->assertSessionHasNoErrors();

    return Event::query()->sole();
}

it('creates an event with proposed dates instead of a date', function (): void {
    $event = pollEvent($this->host, $this->guild);

    expect($event->date_polling)->toBeTrue()
        ->and($event->dateOptions()->count())->toBe(2)
        ->and($event->starts_at->toIso8601String())->toBe('2026-12-05T17:00:00+00:00');
});

it('needs a date or at least two proposed dates', function (): void {
    $this->actingAs($this->host)->post(route('events.store'), [
        'guild_id' => $this->guild->id, 'title' => 'x', 'timezone' => 'UTC', 'date_options' => [['starts_at' => '2026-12-05T18:00']],
    ])->assertSessionHasErrors('date_options');

    $this->actingAs($this->host)->post(route('events.store'), ['guild_id' => $this->guild->id, 'title' => 'x', 'timezone' => 'UTC'])
        ->assertSessionHasErrors('starts_at');
});

it('announces the proposed dates in Discord with a vote button instead of RSVP buttons, and no native event', function (): void {
    $event = pollEvent($this->host, $this->guild);

    $message = $this->discord->sentMessages()[0]['message'];
    expect(array_column($message['components'][0]['components'], 'custom_id'))->toBe([EventCustomId::dates($event->id)])
        ->and($message['embeds'][0]['fields'][0]['value'])->toContain('Not decided yet')
        ->and($this->discord->scheduledEvents())->toBe([]);
});

it('takes votes, shows the best date and keeps RSVPs closed until a date is chosen', function (): void {
    $event = pollEvent($this->host, $this->guild);
    [$early, $late] = $event->dateOptions()->get()->all();

    $this->actingAs($this->anna)->put(route('events.dates.vote', [$event, $early]), ['answer' => 'yes']);
    $this->actingAs($this->bo)->put(route('events.dates.vote', [$event, $early]), ['answer' => 'if_need_be']);
    $this->actingAs($this->bo)->put(route('events.dates.vote', [$event, $late]), ['answer' => 'yes']);
    $this->actingAs($this->anna)->put(route('events.dates.vote', [$event, $late]), ['answer' => 'no']);

    $this->actingAs($this->anna)->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('date_poll.0.yes', 1)->where('date_poll.0.if_need_be', 1)->where('date_poll.0.best', true)
            ->where('date_poll.1.best', false)
            ->where('date_poll.0.mine', 'yes'));

    $this->actingAs($this->anna)->put(route('events.rsvp', $event), ['status' => 'going'])->assertSessionHasErrors('status');
});

it('turns votes into RSVPs when the organiser chooses a date', function (): void {
    $event = pollEvent($this->host, $this->guild);
    $early = $event->dateOptions()->firstOrFail();
    $this->actingAs($this->anna)->put(route('events.dates.vote', [$event, $early]), ['answer' => 'yes']);
    $this->actingAs($this->bo)->put(route('events.dates.vote', [$event, $early]), ['answer' => 'if_need_be']);

    $this->actingAs($this->anna)->post(route('events.dates.choose', [$event, $early]))->assertForbidden();
    $this->actingAs($this->host)->post(route('events.dates.choose', [$event, $early]))->assertRedirect();

    $event->refresh();
    expect($event->date_polling)->toBeFalse()
        ->and($event->ends_at?->toIso8601String())->toBe('2026-12-05T22:00:00+00:00')
        ->and(EventRsvp::query()->where('discord_user_id', $this->anna->discord_user_id)->value('status'))->toBe(RsvpStatus::Going)
        ->and(EventRsvp::query()->where('discord_user_id', $this->bo->discord_user_id)->value('status'))->toBe(RsvpStatus::Maybe)
        ->and(EventRsvp::query()->where('discord_user_id', $this->host->discord_user_id)->value('status'))->toBe(RsvpStatus::Going)
        ->and($this->discord->scheduledEvents())->toHaveCount(1)
        ->and(array_last($this->discord->editedMessages())['message']['components'] ?? [])->not->toBe([]);
});

it('lets organisers propose and withdraw dates, but not the last one', function (): void {
    $event = pollEvent($this->host, $this->guild);

    $this->actingAs($this->anna)->post(route('events.dates.store', $event), ['starts_at' => '2026-12-19T18:00'])->assertForbidden();
    $this->actingAs($this->host)->post(route('events.dates.store', $event), ['starts_at' => '2026-12-01T18:00'])->assertSessionHasNoErrors();
    expect($event->fresh()?->starts_at->toIso8601String())->toBe('2026-12-01T17:00:00+00:00');

    foreach ($event->dateOptions()->get() as $option) {
        $this->actingAs($this->host)->delete(route('events.dates.destroy', [$event, $option]));
    }

    expect(EventDateOption::query()->count())->toBe(1);
});

it('keeps the date when editing other details during the vote, and sends no reminders', function (): void {
    $event = pollEvent($this->host, $this->guild);
    EventRsvp::query()->where('event_id', $event->id)->update(['status' => 'going']);
    $this->actingAs($this->host)->put(route('events.update', $event), ['title' => 'Crayfish!', 'starts_at' => '2026-11-28T10:00', 'timezone' => 'Europe/Stockholm']);

    expect($event->fresh()?->starts_at->toIso8601String())->toBe('2026-12-05T17:00:00+00:00');

    Date::setTestNow('2026-12-05T10:00:00Z');
    dispatch_sync(new SendEventReminders);
    expect($this->discord->directMessages())->toBe([]);
});

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function dateComponent(User $user, array $data): array
{
    return [
        'id' => 'i', 'token' => 'tok', 'type' => 3, 'guild_id' => '555',
        'member' => P::member($user->discordUser->discord_id),
        'data' => $data,
    ];
}

it('opens a private date picker from the announcement button', function (): void {
    $event = pollEvent($this->host, $this->guild);
    [$early] = $event->dateOptions()->get()->all();
    $this->actingAs($this->anna)->put(route('events.dates.vote', [$event, $early]), ['answer' => 'yes']);

    expect(InteractionAck::body(dateComponent($this->anna, ['custom_id' => EventCustomId::dates($event->id), 'component_type' => 2])))->toBe(['type' => 5, 'data' => ['flags' => 64]]);
    app(EventInteractions::class)->handle(dateComponent($this->anna, ['custom_id' => EventCustomId::dates($event->id), 'component_type' => 2]));

    $picker = $this->discord->lastResponse();
    $yes = $picker['components'][0]['components'][0];
    expect($yes['custom_id'])->toBe(EventCustomId::datesYes($event->id))
        ->and(array_column($yes['options'], 'label'))->toBe(['Sat 5 Dec, 18:00–23:00', 'Sat 12 Dec, 18:00'])
        ->and(array_column($yes['options'], 'default'))->toBe([true, false]);
});

it('saves votes from the picker selects and updates the tallies', function (): void {
    $event = pollEvent($this->host, $this->guild);
    [$early, $late] = $event->dateOptions()->get()->all();

    expect(InteractionAck::body(dateComponent($this->anna, ['custom_id' => EventCustomId::datesYes($event->id), 'component_type' => 3, 'values' => []])))->toBe(['type' => 6]);

    app(EventInteractions::class)->handle(dateComponent($this->anna, ['custom_id' => EventCustomId::datesMaybe($event->id), 'component_type' => 3, 'values' => [(string) $late->id]]));
    app(EventInteractions::class)->handle(dateComponent($this->anna, ['custom_id' => EventCustomId::datesYes($event->id), 'component_type' => 3, 'values' => [(string) $early->id]]));

    $votes = EventDateVote::query()->where('discord_user_id', $this->anna->discord_user_id)->get()->mapWithKeys(fn (EventDateVote $v): array => [$v->event_date_option_id => $v->answer->value])->all();
    expect($votes)->toBe([$late->id => 'if_need_be', $early->id => 'yes'])
        ->and(FakeDiscordClient::content($this->discord->lastResponse()))->toStartWith('Saved.');

    $announcement = array_last($this->discord->editedMessages())['message'];
    expect($announcement['embeds'][0]['fields'][0]['value'])->toContain('(1 yes, 0 if need be)')->toContain('(0 yes, 1 if need be)');

    app(EventInteractions::class)->handle(dateComponent($this->anna, ['custom_id' => EventCustomId::datesYes($event->id), 'component_type' => 3, 'values' => []]));
    expect(EventDateVote::query()->where('event_date_option_id', $early->id)->value('answer'))->toBe(DateVote::No)
        ->and(EventDateVote::query()->where('event_date_option_id', $late->id)->value('answer'))->toBe(DateVote::IfNeedBe);
});

it('says the date is decided once it is', function (): void {
    $event = pollEvent($this->host, $this->guild);
    $this->actingAs($this->host)->post(route('events.dates.choose', [$event, $event->dateOptions()->firstOrFail()]));

    app(EventInteractions::class)->handle(dateComponent($this->anna, ['custom_id' => EventCustomId::dates($event->id), 'component_type' => 2]));

    expect(FakeDiscordClient::content($this->discord->lastResponse()))->toContain('already decided');
});

it('DMs everyone who voted where they stand once a date is chosen', function (): void {
    $event = pollEvent($this->host, $this->guild);
    [$early, $late] = $event->dateOptions()->get()->all();
    $this->actingAs($this->anna)->put(route('events.dates.vote', [$event, $early]), ['answer' => 'yes']);
    $this->actingAs($this->bo)->put(route('events.dates.vote', [$event, $late]), ['answer' => 'yes']);
    $this->actingAs($this->host)->put(route('events.dates.vote', [$event, $early]), ['answer' => 'yes']);

    $this->actingAs($this->host)->post(route('events.dates.choose', [$event, $early]));

    $dmTo = fn (User $user): string => FakeDiscordClient::content(collect($this->discord->directMessages())->firstWhere('user_id', $user->discordUser->discord_id)['message'] ?? []);

    expect($this->discord->directMessages())->toHaveCount(2)
        ->and($dmTo($this->anna))->toContain("You're down as going")
        ->and($dmTo($this->bo))->toContain('Can you make it after all?')
        ->and($dmTo($this->host))->toBe('');
});
