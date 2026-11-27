---
paths:
    - 'app/*/Testing/**'
---

# Testing

## Test doubles

Don't use Mockery. Use framework fakes (`Queue::fake()`, `Http::fake()`, agent fakes) or a hand-written in-memory `Fake*` implementation of the app's interface, kept in that domain's `Testing` folder and bound with `app()->instance()`.
