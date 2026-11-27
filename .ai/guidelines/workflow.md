# friendly-friend: workflow

**Conventions and design decisions live in `.ai/rules/`** (start with `.ai/rules/general.md`; `.ai/rules/index.md` maps paths to rule files). Read the matching rules before editing.

## Keep the knowledge current (same commit, always)

- A commit that introduces or changes a convention, a design decision or a trap updates the matching `.ai/rules/*.md` file. New areas get a new rule file with `paths` frontmatter, then run `vendor/bin/sail artisan boost:index-rules`.
- A commit that changes commands, schema, config, architecture or a feature updates `docs/**`.
- Bugs, follow-ups, upstream issues and ideas go into `ROADMAP.md` (open items only). Done items move to `COMPLETED.md` in the same commit.
- A change in behaviour updates **every description of it** in the same commit: `docs/**`, rules, UI copy and help text, and the AI agents' instructions and tool descriptions (the models read those, so a stale one changes what they do). Treat a stale description as a review finding.
- After changing anything in `.ai/`, run `vendor/bin/sail artisan boost:update` so `AGENTS.md`/`CLAUDE.md` are regenerated. CI warns on PRs that change code without docs or rules.

## Commands

- Run **every** PHP, Composer, artisan and npm command through Sail (`vendor/bin/sail …`). Host PHP is 8.3, but the project needs PHP 8.5.
- Agents: pass `CLAUDECODE=1` into the container so `laravel/pao` (dev) turns Pest, Paratest, PHPStan and Rector output into one JSON line: `vendor/bin/sail bash -c 'CLAUDECODE=1 php artisan test --compact …'` (Sail and `docker exec` don't forward host variables; use `docker exec -e CLAUDECODE=1 …`). Exit codes are the same either way. A script that reads the human `Tests:` line must not set it.
- After backend changes that affect types (models, enums, requests, resources, Inertia actions, broadcast events): `vendor/bin/sail artisan typefinder:generate`, and commit `resources/js/typefinder/`.

## Skills for procedures

- Formatting or cleaning up code: the `formatting-project` skill (Rector, then Vite+, then Pint, keeping every change they make).
- Running browser tests: the `running-browser-tests` skill.
- Working in a git worktree (setup in Sail, its own test database, cleanup): the `working-in-a-worktree` skill.
- **When your pull request is merged, clean up after yourself in the same session**: your Playwright servers, test databases, worktree and branch (the `cleaning-up-after-a-merge` skill). Only ever what is yours.

## Before every commit

- `vendor/bin/sail artisan typefinder:generate --check --strict`
- `vendor/bin/sail composer test` (Rector dry run, Pint, Larastan at max level, Pest)
- `vendor/bin/sail npm run check` and `vendor/bin/sail npm run types:check`
- Pages changed: `vendor/bin/sail npm run build && vendor/bin/sail composer test:browser`, and look at the screenshots

## Habits

- Write the failing test first (Pest). Fix Larastan errors at their cause: no baseline, no `@phpstan-ignore`, no casts or `@var` only to silence it.
- Use conventional commit messages (`feat:`, `fix:`, `chore:`, `docs:`, `ci:`, `build:`, `refactor:`, `test:`). They drive releases: `feat:` cuts a minor version, `fix:` a patch, a `BREAKING CHANGE:` footer a major, and each `feat`/`fix` subject becomes a `CHANGELOG.md` line, so write it for the people who use the site.
- When the implementation deliberately departs from the spec (`docs/superpowers/specs/`), update the spec too.
