---
paths:
    - 'app/*/Jobs/**'
---

# Jobs

## Job payloads

Jobs carry scalar IDs, DTOs or raw payload arrays, never Eloquent models (no `SerializesModels`); re-query the model in `handle()`. Declare retries and uniqueness with attributes (`#[Tries]`, `#[UniqueFor]`, `#[Timeout]`).
