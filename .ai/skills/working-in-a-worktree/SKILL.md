---
name: working-in-a-worktree
description: 'Use when working in a git worktree of friendly-friend (for example under .claude/worktrees/): setting it up to run artisan, Composer, npm and tests in Sail, giving it its own test database, and cleaning up when it is removed.'
---

# Working in a worktree

Sail runs once, from the main checkout. Its `laravel.test` container mounts the main checkout at `/var/www/html`, so a worktree at `.claude/worktrees/<name>` is `/var/www/html/.claude/worktrees/<name>` inside it. A fresh worktree has no `vendor/`, so `vendor/bin/sail` isn't there: run commands with `docker exec` in the worktree's folder instead.

```bash
docker exec -u sail -w /var/www/html/.claude/worktrees/<name> friendly-friend-laravel.test-1 <command>
```

Always pass `-u sail`. A command run as root leaves root-owned files in shared folders (`/tmp/cache/PHPStan`), and Rector then skips files with "Permission denied" for every session. Fix that with `docker exec -u root friendly-friend-laravel.test-1 chown -R sail:sail /tmp/cache`.

## Set up

1. Install: `composer install` and `npm ci` (through the `docker exec` above).
2. Copy the main checkout's `.env` into the worktree (it is git-ignored, never commit it).
3. Build: `npm run build`. Without the Vite manifest, every Inertia page test answers 500.
4. Create the worktree's own test database, so its runs never collide with the main checkout's or another worktree's: `docker exec friendly-friend-pgsql-1 psql -U sail -d postgres -c 'CREATE DATABASE testing_<name>'`.

## Run

- Pass the database on every test run, and `CLAUDECODE=1` for compact one-line JSON output from Pest, PHPStan and Rector (`laravel/pao`): `docker exec -u sail -e CLAUDECODE=1 -e DB_DATABASE=testing_<name> -w … friendly-friend-laravel.test-1 composer test`. `phpunit.xml` doesn't force its `testing` value, so the variable wins. Parallel runs add `testing_<name>_test_1`, `_2` and so on.
- Git doesn't work inside the container: the worktree's `.git` file points at a host path. `pint --dirty` fails there, so pass Pint the changed files instead (`vendor/bin/pint --format agent app/… tests/…`). Run git itself on the host.
- Long-running services (Horizon, the bot, Reverb) run the main checkout's code, not the worktree's.

## Clean up

When the worktree is removed, drop its databases: `testing_<name>` and every `testing_<name>_test_*`.
