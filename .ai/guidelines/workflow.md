# friendly-friend: workflow rules

- Run **every** PHP, Composer, artisan and npm command through Sail: `vendor/bin/sail artisan …`, `vendor/bin/sail composer …`, `vendor/bin/sail npm …`. Host PHP is 8.3, but the project needs PHP 8.5.
- **Rules and docs are part of every change.** A commit that changes conventions, commands, schema, config, architecture or a feature must update the affected `.ai/guidelines/*.md` and `docs/**` **in the same commit**, then run `vendor/bin/sail artisan boost:update` so `CLAUDE.md`/`AGENTS.md` are regenerated. Never leave this for a later commit. On PRs, CI warns when code changes without any docs or guideline change.
- After changing models, enums, form requests, API resources, Inertia controller actions or broadcast events, run `vendor/bin/sail artisan typefinder:generate` and commit `resources/js/typefinder/`. CI fails on drift (`typefinder:generate --check --strict`).
- Before committing, everything here must pass:
    - `vendor/bin/sail artisan typefinder:generate --check --strict`
    - `vendor/bin/sail composer test` (Rector dry run, Pint, Larastan at max level, Pest)
    - `vendor/bin/sail npm run check` and `vendor/bin/sail npm run types:check`
- Fix Larastan errors at their cause. No baseline, no `@phpstan-ignore`, no casts or `@var` added only to silence it.
- Tests use Pest. Write the failing test first (TDD). Feature tests use Postgres via `RefreshDatabase`, configured in `tests/Pest.php`.
- Use conventional commit messages (`feat:`, `fix:`, `chore:`, `docs:`, `ci:`, `build:`, `refactor:`, `test:`).
- The design spec lives in `docs/superpowers/specs/` and the phase plans in `docs/superpowers/plans/`. When the implementation deliberately departs from the spec, update the spec too.
- **Dark mode is required.** Every page and component must look right in light and dark (the default appearance is "system"). Use the theme tokens (`bg-background`, `text-muted-foreground`, `border`…) and `dark:` variants instead of fixed colours. New pages get a Pest browser test that also renders them in dark mode.
