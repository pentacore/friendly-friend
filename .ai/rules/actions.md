---
paths:
    - 'app/*/Actions/**'
---

# Actions

## Action classes

Actions are `final readonly` classes with a single public `handle()` method; inject their dependencies through the constructor. Callers receive the action by injection (constructor, or a job/controller method) and call `->handle(...)`.
