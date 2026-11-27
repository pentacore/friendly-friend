---
paths:
    - 'app/*/Actions/**'
---

# Actions

## Action classes

Actions are `final readonly` classes with a single public `handle()` method; inject their dependencies through the constructor. Callers receive the action by injection (constructor, or a job/controller method) and call `->handle(...)`.

Shared helpers that aren't actions (two or more public methods, like `Planning\EventAttributes`) live next to the `Actions` folder, not inside it.
