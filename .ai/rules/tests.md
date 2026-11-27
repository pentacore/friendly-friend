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
