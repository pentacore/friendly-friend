<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Guild;
use App\Models\User;

beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
    $this->user = User::factory()->memberOf($this->guild)->create();
});

it('downloads an event as an .ics file', function (): void {
    $event = Event::factory()->create([
        'guild_id' => $this->guild->id,
        'title' => 'Crayfish party, with sauna; bring a towel',
        'location' => 'The cabin',
        'description' => "Line one\nLine two",
        'starts_at' => '2026-12-05T17:00:00Z',
        'ends_at' => null,
    ]);

    $response = $this->actingAs($this->user)->get(route('events.calendar', $event))->assertOk();
    $ics = (string) $response->getContent();

    expect($response->headers->get('Content-Type'))->toStartWith('text/calendar')
        ->and($ics)->toContain("BEGIN:VCALENDAR\r\n")
        ->toContain("DTSTART:20261205T170000Z\r\n")
        ->toContain("DTEND:20261205T230000Z\r\n")
        ->toContain('SUMMARY:Crayfish party\, with sauna\; bring a towel')
        ->toContain('LOCATION:The cabin')
        ->toContain('Line one\nLine two')
        ->toContain("STATUS:CONFIRMED\r\n");

    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }
});

it('marks cancelled events and refuses undecided dates and other servers', function (): void {
    $cancelled = Event::factory()->cancelled()->create(['guild_id' => $this->guild->id]);
    $polling = Event::factory()->create(['guild_id' => $this->guild->id, 'date_polling' => true]);
    $other = Event::factory()->create();

    expect((string) $this->actingAs($this->user)->get(route('events.calendar', $cancelled))->getContent())->toContain('STATUS:CANCELLED');
    $this->actingAs($this->user)->get(route('events.calendar', $polling))->assertNotFound();
    $this->actingAs($this->user)->get(route('events.calendar', $other))->assertForbidden();
});
