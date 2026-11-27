---
paths:
    - 'app/**'
---

# App

## Final classes

Make every class `final`, and `final readonly` when it holds no mutable state. Models, service providers, middleware and the base `Controller` stay non-final.

## Strict types

Start every PHP file with `declare(strict_types=1);`.

## Dispatching jobs

Dispatch jobs with the helper, `dispatch(new SomeJob(...))`, not `SomeJob::dispatch(...)`.

## Eloquent entry point

Start every Eloquent query or write with `Model::query()` (`Guild::query()->where(...)`, `User::query()->firstOrCreate(...)`), never static magic calls like `Guild::where(...)`.

## Class docblocks

Give each class a short docblock above its declaration saying what it is for and where it sits in the flow.

## No events, services or repositories

There is no Events/Listeners layer: chain work by dispatching jobs directly. There is no Services or repository layer: actions and jobs query Eloquent directly.
