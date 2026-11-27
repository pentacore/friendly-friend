<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\QuoteStatus;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\Guild;
use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteImport;
use App\Models\QuoteLine;
use App\Models\User;

/**
 * The pages the browser layout gate (tests/Browser/LayoutGateTest.php) opens
 * on a phone and a desktop, in light and dark mode, and the fixtures they
 * need. `LayoutGateRoutesTest` fails while a named GET page route is in
 * neither list, so a new page joins the gate.
 */
final class LayoutGate
{
    /**
     * Page route name => the fixture parameter it needs (null for none).
     *
     * @var array<string, string|null>
     */
    public const array PAGES = [
        'login' => null,
        'dashboard' => null,
        'quotes.index' => null,
        'quotes.show' => 'quote',
        'people.show' => 'person',
        'this-or-that.show' => null,
        'top' => null,
        'stats' => null,
        'events.index' => null,
        'events.create' => null,
        'events.show' => 'event',
        'events.edit' => 'event',
        'appearance.edit' => null,
        'food-settings.edit' => null,
        'privacy.edit' => null,
        'admin.guilds.index' => null,
        'admin.settings.index' => null,
        'admin.people.index' => null,
        'admin.imports.index' => null,
        'admin.imports.show' => 'import',
        'admin.removed-quotes.index' => null,
    ];

    /**
     * Named GET routes that aren't pages, and why.
     *
     * @var array<string, string>
     */
    public const array EXCLUDED = [
        'home' => 'redirects to the dashboard',
        'quotes.random' => 'redirects to a random quote page',
        'events.calendar' => 'an .ics download',
        'auth.discord.redirect' => 'redirects to Discord',
        'auth.discord.callback' => 'the OAuth callback',
    ];

    /**
     * An owner (so every admin page opens) who is a member of a server with
     * a quote, a person, an event and a finished channel import.
     *
     * @return array{user: User, parameters: array<string, int|string>}
     */
    public static function seed(): array
    {
        config()->set('discord.owner_ids', ['4242']);
        $guild = Guild::factory()->create(['name' => 'Claesson Notify']);
        $user = User::factory()->forDiscordUser(DiscordUser::factory()->create(['discord_id' => '4242', 'global_name' => 'Martin']))->memberOf($guild)->create();

        $person = Person::createNamed('Martin');
        $quote = Quote::factory()->for($guild)->create(['context' => 'at the barbecue']);
        QuoteLine::factory()->for($quote)->create(['position' => 0, 'speaker_name' => 'Martin', 'person_id' => $person->id, 'text' => "I'm not drunk, I'm philosophical"]);
        $quote->refreshSearchText();

        $event = Event::factory()->create([
            'guild_id' => $guild->id,
            'created_by_discord_user_id' => $user->discord_user_id,
            'title' => 'Crayfish party at the cabin by the lake',
            'location' => 'The cabin',
        ]);

        $import = QuoteImport::query()->create([
            'guild_id' => $guild->id,
            'channel_id' => '321',
            'requested_by_discord_user_id' => $user->discord_user_id,
            'status' => 'done',
            'messages_scanned' => 120,
            'quotes_found' => 1,
        ]);
        $pending = Quote::factory()->for($guild)->create(['status' => QuoteStatus::PendingReview, 'import_id' => $import->id]);
        QuoteLine::factory()->for($pending)->create(['position' => 0, 'speaker_name' => 'Bo', 'text' => 'I can totally fix the dishwasher']);

        return [
            'user' => $user,
            'parameters' => ['quote' => $quote->id, 'person' => $person->slug, 'event' => $event->id, 'import' => $import->id],
        ];
    }

    /**
     * Fails on content wider than the screen: the page must never scroll sideways.
     */
    public const string OVERFLOW_SCRIPT = <<<'JS'
        (() => {
            const width = document.documentElement.clientWidth;
            return [...document.querySelectorAll('body *')]
                .filter((element) => {
                    const box = element.getBoundingClientRect();
                    if (box.width === 0 || box.height === 0) return false;
                    for (let parent = element.parentElement; parent; parent = parent.parentElement) {
                        const overflow = getComputedStyle(parent).overflowX;
                        if (overflow === 'auto' || overflow === 'scroll' || overflow === 'hidden' || overflow === 'clip') return false;
                    }
                    return box.right > width + 1;
                })
                .slice(0, 5)
                .map((element) => element.tagName.toLowerCase() + (element.dataset.test ? `[data-test="${element.dataset.test}"]` : '') + ' ' + (element.textContent ?? '').trim().slice(0, 40))
                .join(' | ');
        })()
        JS;

    /**
     * Fails on a visible button or link without an accessible name (an icon-only control needs aria-label or sr-only text).
     */
    public const string UNNAMED_CONTROL_SCRIPT = <<<'JS'
        (() => [...document.querySelectorAll('button, a[href], [role="button"]')]
            .filter((element) => {
                const box = element.getBoundingClientRect();
                if (box.width === 0 || box.height === 0 || getComputedStyle(element).visibility === 'hidden') return false;
                const labelledBy = (element.getAttribute('aria-labelledby') ?? '').split(' ').map((id) => document.getElementById(id)?.textContent ?? '').join('');
                // A button (reka-ui's checkbox is one) is also named by a wrapping <label> or label[for].
                const labels = [...(element.labels ?? [])].map((label) => label.textContent ?? '').join('');
                const name = (element.getAttribute('aria-label') ?? '') + labelledBy + labels + (element.textContent ?? '') + (element.getAttribute('title') ?? '')
                    + [...element.querySelectorAll('img[alt]')].map((image) => image.getAttribute('alt')).join('');
                return name.trim() === '';
            })
            .slice(0, 5)
            .map((element) => element.outerHTML.slice(0, 120))
            .join(' | '))()
        JS;
}
