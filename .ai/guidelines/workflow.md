# friendly-friend: workflow

**Conventions and design decisions live in `.ai/rules/`** (start with `.ai/rules/general.md`; `.ai/rules/index.md` maps paths to rule files). Read the matching rules before editing.

## Keep the knowledge current (same commit, always)

- A commit that introduces or changes a convention, a design decision or a trap updates the matching `.ai/rules/*.md` file. New areas get a new rule file with `paths` frontmatter, then run `vendor/bin/sail artisan boost:index-rules`.
- A commit that changes commands, schema, config, architecture or a feature updates `docs/**`.
- Bugs, follow-ups, upstream issues and ideas go into `ROADMAP.md`.
- After changing anything in `.ai/`, run `vendor/bin/sail artisan boost:update` so `AGENTS.md`/`CLAUDE.md` are regenerated. CI warns on PRs that change code without docs or rules.

## Commands

- Run **every** PHP, Composer, artisan and npm command through Sail (`vendor/bin/sail …`). Host PHP is 8.3, but the project needs PHP 8.5.
- After backend changes that affect types (models, enums, requests, resources, Inertia actions, broadcast events): `vendor/bin/sail artisan typefinder:generate`, and commit `resources/js/typefinder/`.

## Before every commit

- `vendor/bin/sail artisan typefinder:generate --check --strict`
- `vendor/bin/sail composer test` (Rector dry run, Pint, Larastan at max level, Pest)
- `vendor/bin/sail npm run check` and `vendor/bin/sail npm run types:check`
- Pages changed: `vendor/bin/sail npm run build && vendor/bin/sail composer test:browser`, and look at the screenshots

## Habits

- Write the failing test first (Pest). Fix Larastan errors at their cause: no baseline, no `@phpstan-ignore`, no casts or `@var` only to silence it.
- Use conventional commit messages (`feat:`, `fix:`, `chore:`, `docs:`, `ci:`, `build:`, `refactor:`, `test:`).
- When the implementation deliberately departs from the spec (`docs/superpowers/specs/`), update the spec too.
