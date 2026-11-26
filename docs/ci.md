# Continuous integration

GitHub Actions workflow: `.github/workflows/ci.yml`. It runs on pushes to `main` and on pull requests.

## `ci` job (PHP 8.5, Node 24, Postgres 18, Redis)

Steps, in order:

1. `composer install`, `npm ci`
2. `.env` from `.env.example`, `key:generate`, `migrate`
3. **Typefinder drift check**: `php artisan typefinder:generate --check --strict`. The generated types in `resources/js/typefinder/` are committed. The job fails if they're out of date (a model, enum, request, resource, Inertia page or broadcast event changed without regenerating) or if the generator printed any warning.
4. `composer lint:check` (Rector dry run, Pint test)
5. `composer types:check` (Larastan at max level)
6. `npm run build` (also generates the Wayfinder routes the type check needs)
7. `npm run check` (lint and format), `npm run types:check` (vue-tsc)
8. `php artisan test --parallel` (Pest)

Run the same checks locally before pushing:

```bash
vendor/bin/sail artisan typefinder:generate --check --strict
vendor/bin/sail composer test
vendor/bin/sail npm run check
vendor/bin/sail npm run types:check
```

## `docs-updated` job (pull requests only)

`scripts/check-docs-updated.sh` posts a **warning** (it never fails the build) when `app/`, `database/`, `routes/` or `config/` changed but nothing under `docs/` or `.ai/` did. Rules and docs ship in the same change as the code.
