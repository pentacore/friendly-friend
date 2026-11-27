# Continuous integration

GitHub Actions workflow: `.github/workflows/ci.yml`. It runs on pushes to `main` and on pull requests.

## `ci` job (PHP 8.5, Node 24, Postgres 18, Redis)

Steps, in order:

1. `composer install`, `npm ci`
2. `.env` from `.env.example`, `key:generate`, `migrate`
3. **Typefinder drift check**: `php artisan typefinder:generate --check --strict`. The generated types in `resources/js/typefinder/` are committed. The job fails if they're out of date (a model, enum, request, resource, Inertia page or broadcast event changed without regenerating) or if the generator printed any warning.
4. `composer lint:check` (Rector dry run, Pint test)
5. `composer types:check` (Larastan at max level)
6. `npm run build:ssr` (client and SSR bundles; also generates the Wayfinder routes the type check needs)
7. `npm run check` (lint and format), `npm run types:check` (vue-tsc)
8. `php artisan test --parallel` (Pest, Unit and Feature)

## `browser` job (3 shards)

The browser suite runs in 3 parallel shards (`php artisan test --testsuite=Browser --shard=i/3`), balanced by the per-class timings in `tests/.pest/shards.json`. Each shard installs the app, builds the client and SSR bundles, installs Chromium (`npx playwright install --with-deps chromium`) and starts the SSR server (`php artisan inertia:start-ssr`). The suite covers:

- the layout gate (`LayoutGateTest`): every page on a phone and a desktop, light and dark, with no JavaScript errors, no sideways overflow, the dark theme applied and every control named
- page tests in light and dark mode, with screenshots
- the hydration check: server-rendered pages hydrated in a browser with another locale and timezone must not mismatch

A failed shard uploads its screenshots as the `browser-screenshots-<shard>` artifact. Refresh the timings after adding or renaming a browser test class: `php artisan test --testsuite=Browser --update-shards`.

Run the same checks locally before pushing:

```bash
vendor/bin/sail artisan typefinder:generate --check --strict
vendor/bin/sail composer test
vendor/bin/sail npm run check
vendor/bin/sail npm run types:check
```

## `image` job

Builds the production `Dockerfile` (target `app`) and runs `php -v` in it, so a broken image fails CI before a deploy.

## `docs-updated` job

`scripts/check-docs-updated.sh` posts a **warning** (it never fails the build) when `app/`, `database/`, `routes/` or `config/` changed but nothing under `docs/` or `.ai/` did. Pull requests compare with their base branch; pushes compare with the commit before the push (a branch's first push is skipped). Rules and docs ship in the same change as the code.

## Dependabot

`.github/dependabot.yml` opens weekly pull requests for Composer, npm and GitHub Actions. Each waits 5 days after a release (security updates don't wait). Minor and patch updates are grouped per ecosystem and dependency type; majors arrive one by one. Held updates are `ignore` entries with the reason in a comment, listed under "Held dependency updates" in `ROADMAP.md`. A new production package needs the owner's approval first (`.ai/rules/dependencies.md`).
