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

## Raw redirect headers

`assertRedirect()` normalises relative URLs. When the scheme or host matters (e.g. trusted proxies), assert the raw `Location` header.
