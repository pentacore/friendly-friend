---
paths:
    - 'database/seeders/**'
---

# Seeders

## Seeders are idempotent and local-only

Real data comes from Discord (`discord:sync-members`, the bot) and tests build theirs with factories, so seeders only add local conveniences. A seeder must be safe to run twice (`firstOrCreate()` / `updateOrCreate()` on a natural key, never a bare `create()`) and returns early outside the `local` environment.
