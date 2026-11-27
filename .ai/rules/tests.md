---
paths:
    - 'tests/**'
---

# Tests

## Strict types

Start every PHP file with `declare(strict_types=1);`.

## Eloquent entry point

Start every Eloquent query or write with `Model::query()` (`Guild::query()->where(...)`, `User::query()->firstOrCreate(...)`), never static magic calls like `Guild::where(...)`.

## Test doubles

Don't use Mockery. Use framework fakes (`Queue::fake()`, `Http::fake()`, agent fakes) or a hand-written in-memory `Fake*` implementation of the app's interface, kept in that domain's `Testing` folder and bound with `app()->instance()`.

## Building Discord payloads

Build Discord gateway and interaction payloads with `Tests\Support\DiscordPayloads`, and recorded gateway events with the JSON fixtures in `tests/Fixtures/gateway/`. Don't inline large payload arrays.

## Comparing stored JSON

Postgres `jsonb` reorders keys, so compare stored JSON with `toEqual`, not `toBe`.

## Browser tests

`tests/Browser` is a separate suite, not part of the default run (`composer test:browser`). It renders the **built** assets (`npm run build` first). Cover light and dark mode, assert no JavaScript errors, and take screenshots.

## The layout gate covers every page

`tests/Browser/LayoutGateTest.php` opens every page in `Tests\Support\LayoutGate::PAGES` on a phone (375×812) and a desktop (MacBook Air), in light and dark mode, and fails on JavaScript errors, content wider than the screen, the dark theme not following the browser, and a visible button or link without an accessible name. `LayoutGateRoutesTest` fails while a named GET page route is in neither `PAGES` nor `EXCLUDED` (with its reason), so add a row with the page. Give a detail page its fixture in `LayoutGate::seed()`. Fix the layout rather than loosen a check.

## Keep the shard timings current

CI runs the browser suite in 3 duration-balanced shards (`--shard=i/3`), using the per-class timings in `tests/.pest/shards.json`. A class missing from the file is dealt out round-robin and the shard log warns that the file is out of date. After adding, splitting or renaming a browser test class, refresh it with `php artisan test --testsuite=Browser --update-shards` (serially; Pest only writes it after a fully green run) and commit it.

## Raw redirect headers

`assertRedirect()` normalises relative URLs. When the scheme or host matters (e.g. trusted proxies), assert the raw `Location` header.

## No real services

Feature and browser tests block stray HTTP (`Http::preventStrayRequests()` in `tests/Pest.php`; the browser suite also blanks the bot token, since the app under test runs in the same process). `phpunit.xml` turns Inertia SSR off and blanks `DISCORD_BOT_TOKEN`, so a real `.env` can't make tests act as the bot. Fake Discord with `FakeDiscordClient` / `FakeInteractionAcknowledger`, and HTTP with `Http::fake()`.
