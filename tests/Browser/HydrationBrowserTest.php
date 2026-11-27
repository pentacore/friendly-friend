<?php

declare(strict_types=1);

use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Guild;
use App\Models\Quote;
use App\Models\StatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Pages with dates and numbers, rendered by the SSR server and hydrated in
 * a browser whose locale and timezone differ from the server's. Vue
 * reports any difference with console.error/warn, which the app records in
 * window.__hydrationMismatches (lib/hydrationCheck.ts): the browser
 * plugin's own console capture only sees console.log.
 *
 * Needs the SSR server (npm run build:ssr, then php artisan
 * inertia:start-ssr); skipped without it. CI starts it.
 */
beforeEach(function (): void {
    $server = @fsockopen('127.0.0.1', 13714, timeout: 1);

    if ($server === false) {
        $this->markTestSkipped('No SSR server on 127.0.0.1:13714 (npm run build:ssr && php artisan inertia:start-ssr).');
    }

    fclose($server);
    config()->set('inertia.ssr.enabled', true);
    Http::allowStrayRequests(['http://127.0.0.1:13714/*']);

    $this->guild = Guild::factory()->create(['name' => 'Claesson Notify']);
    $this->user = User::factory()->memberOf($this->guild, ['900'])->create();
    $this->event = Event::factory()->create([
        'guild_id' => $this->guild->id,
        'created_by_discord_user_id' => $this->user->discord_user_id,
        // Late evening in Stockholm: already the next day in UTC, still the same day in New York.
        'starts_at' => now()->addDays(5)->setTime(22, 30),
        'rsvp_by' => now()->addDays(2)->setTime(23, 15),
    ]);
    EventRsvp::query()->create(['event_id' => $this->event->id, 'discord_user_id' => $this->user->discord_user_id, 'status' => 'going']);
    $this->quote = Quote::factory()->for($this->guild)->withLine('Bo', 'cheese is a vegetable')->create(['said_at' => now()->subDays(3)->setTime(23, 30), 'said_at_precision' => 'day']);
    StatMessage::factory()->create(['guild_id' => $this->guild->id, 'discord_user_id' => DiscordUser::factory()->create()->id, 'hour' => now()->subDay()->startOfHour(), 'count' => 1234]);
});

it('hydrates pages with dates and numbers without mismatches', function (string $path): void {
    $this->actingAs($this->user);

    $page = visit(str_replace(['{event}', '{quote}'], [(string) $this->event->id, (string) $this->quote->id], $path))
        ->withLocale('sv-SE')
        ->withTimezone('America/New_York')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector("#app")?.hasAttribute("data-server-rendered")'))->toBeTrue()
        ->and($page->script('window.__hydrationMismatches ?? null'))->toBe([]);
})->with([
    'dashboard' => '/dashboard',
    'events' => '/events',
    'event' => '/events/{event}',
    'quote' => '/quotes/{quote}',
    'quotes' => '/quotes',
    'stats' => '/stats',
]);
