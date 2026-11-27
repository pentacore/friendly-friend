<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Guild;
use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteVote;
use App\Models\StatMessage;
use App\Models\StatVoiceSession;
use App\Models\User;
use Illuminate\Support\Facades\Date;

/**
 * "Download my data" on /settings/privacy: everything stored about you,
 * as JSON, and nothing about anyone else.
 */
beforeEach(function (): void {
    Date::setTestNow('2026-11-27T12:00:00Z');
    $this->guild = Guild::factory()->create(['name' => 'Friends']);
    $this->user = User::factory()->memberOf($this->guild)->create();
    $this->other = User::factory()->memberOf($this->guild)->create();
});

it('downloads your data as JSON', function (): void {
    $me = Person::factory()->forDiscordUser($this->user->discordUser)->create(['display_name' => 'Me']);
    $said = Quote::factory()->for($this->guild)->withLine('Me', 'my own words')->create();
    $said->lines()->update(['person_id' => $me->id]);
    Quote::factory()->for($this->guild)->withLine('Someone', 'not mine')->create(['submitted_by_discord_user_id' => $this->other->discord_user_id]);
    QuoteVote::query()->create(['quote_id' => $said->id, 'user_id' => $this->user->id, 'value' => 1]);
    $event = Event::factory()->create(['guild_id' => $this->guild->id, 'title' => 'Crayfish party']);
    EventRsvp::query()->create(['event_id' => $event->id, 'discord_user_id' => $this->user->discord_user_id, 'status' => 'going', 'note' => 'bringing dill']);
    EventRsvp::query()->create(['event_id' => $event->id, 'discord_user_id' => $this->other->discord_user_id, 'status' => 'maybe', 'note' => 'secret note']);
    StatMessage::factory()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $this->user->discord_user_id, 'hour' => '2026-11-20T10:00:00Z', 'count' => 3]);
    StatMessage::factory()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $this->user->discord_user_id, 'hour' => '2026-11-20T11:00:00Z', 'count' => 4]);
    StatVoiceSession::factory()->create(['guild_id' => $this->guild->id, 'discord_user_id' => $this->user->discord_user_id, 'started_at' => '2026-11-20T18:00:00Z', 'ended_at' => '2026-11-20T19:00:00Z']);

    $response = $this->actingAs($this->user)->get(route('privacy.download'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertDownload('friendly-friend-data-2026-11-27.json');
    $data = $response->json();

    expect($data['account']['discord_id'])->toBe($this->user->discordUser->discord_id)
        ->and($data['servers'][0]['name'])->toBe('Friends')
        ->and($data['person']['name'])->toBe('Me')
        ->and(collect($data['quotes_said'])->pluck('lines.0.text')->all())->toBe(['my own words'])
        ->and($data['quote_votes'])->toBe([['quote_id' => $said->id, 'value' => 1]])
        ->and($data['event_rsvps'][0])->toMatchArray(['event' => 'Crayfish party', 'status' => 'going', 'note' => 'bringing dill'])
        ->and($data['event_rsvps'])->toHaveCount(1)
        ->and($data['stats']['messages_per_day'])->toBe([['server' => 'Friends', 'day' => '2026-11-20', 'messages' => 7]])
        ->and($data['stats']['voice_sessions'])->toHaveCount(1);

    expect((string) $response->getContent())->not->toContain('secret note')->not->toContain('not mine');
});

it('needs a login', function (): void {
    $this->get(route('privacy.download'))->assertRedirect();
});
