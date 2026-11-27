---
paths:
    - 'routes/**'
---

# Routes

## Strict types

Start every PHP file with `declare(strict_types=1);`.

## Public routes

Everything is behind `auth` and `guild.access`, except routes that can't have a session: OAuth callbacks and the personal calendar feed (`calendar.feed`). Such a route takes a secret token, constrains its format with `where()`, is throttled, and re-checks access from the token's owner (`AccessSnapshot::for($user)`) on every request.
