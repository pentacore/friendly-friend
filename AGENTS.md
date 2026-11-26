<laravel-boost-guidelines>
=== .ai/architecture rules ===

# friendly-friend: architecture

A private site and Discord bot for **one** Discord server of friends: quotes (captured from Discord, cleaned up by AI, voted on) and server activity stats with an opt-out. Design: `docs/superpowers/specs/2026-11-26-friendly-friend-design.md`.

## Stack

Laravel 13 on PHP 8.5, Inertia 3 + Vue 3 + TypeScript (vite-plus), PostgreSQL, Redis, Horizon, Reverb, Socialite with socialiteproviders/discord (the only login), Wayfinder (typed routes), pentacore/laravel-typefinder (typed data), DiscordPHP (bot), laravel/ai (OpenAI, Anthropic or Gemini, chosen in `.env`).

## Where code goes (each part has one job)

- `App\Discord\Gateway`: the bot process. `RunBot` (`bot:run`) forwards raw gateway dispatches to `GatewayEventRouter::route($type, $payload)`, which filters to our guild, builds a DTO and **queues a job**. Never put DB queries, HTTP calls or business logic in the router or the command. Add new gateway events as router branches with a JSON fixture in `tests/Fixtures/gateway/` and a test. The opt-out check (Phase 6) also lives in the router.
- `App\Discord\Interactions`: slash commands, the message context menu, buttons and forms. They call actions and build replies.
- `App\Discord\DiscordClient`: interface for **bot-token** REST calls (`HttpDiscordClient`; tests use `Testing\FakeDiscordClient`). Add methods there instead of calling Discord's HTTP API elsewhere. `DiscordUserApi` is only for the OAuth user token at login.
- `App\Quotes\Actions`: `DraftQuote`, `ConfirmQuote`, `EditQuote`, `ResolvePerson`, `RecordVote`, `RecordMatchup`. All quote state changes go through these.
- `App\Ai\QuoteNormalizer`: an interface whose implementation uses laravel/ai structured output. Tests use the fake.
- `App\Stats`: ingest jobs, rollups, `PurgeMemberStats`.
- `App\Access`: who gets in. `GuildAccess` (rules), `Actions\LoginWithDiscord`, `Actions\UpsertDiscordMember`, `Jobs\SyncGuildMembers`, `Exceptions\AccessDeniedException`. The `EnsureGuildAccess` middleware (alias `guild.access`) lives in `App\Http\Middleware`.
- Discord REST returns `App\Discord\Data\*` DTOs. Test the HTTP clients themselves with `Http::fake()`.
- `App\Enums`: backed enums. Typefinder emits them as `as const` objects plus union types.
- Controllers stay thin: validate (FormRequest), call an action, render Inertia.

## Runtime services

Dev (Sail, `compose.yaml`): `laravel.test`, `horizon`, `reverb`, `pgsql`, `redis`, `mailpit`.
Dev also has `bot` behind the compose profile `bot` (`COMPOSE_PROFILES=bot`).
Prod (`compose.prod.yaml`, one FrankenPHP PHP 8.5 image): `web`, `horizon`, `scheduler`, `reverb`, `bot`, `postgres`, `redis`.
The bot and Horizon are long-running: restart them after code changes (`sail restart bot horizon`).
Docs: `docs/architecture.md`, `docs/deployment.md`. The prod image (`Dockerfile`) builds the frontend with `TYPEFINDER_SKIP_BUILD=1`, because the types are committed and there is no DB at build time.

## Dashboards

Horizon and Telescope are open in `local`. Outside `local`, Horizon needs the `admin` gate. Telescope is never registered outside `local`.

=== .ai/domain-rules rules ===

# friendly-friend: domain rules (must always hold)

## Identity

- `discord_members` holds **every** member of the server (synced by the bot), whether or not they ever logged in. `users` are members who have logged in to the website.
- `people` are anyone who can be quoted. `discord_member_id` is **nullable**, because people outside the server can be quoted. Nicknames are stored in `person_aliases`.

## Quotes

- A quote is made of ordered `quote_lines`, each with a person and text, so a back-and-forth exchange is supported.
- Every capture path ends in draft → private preview → confirm. Drafts expire after 15 minutes. Channel imports become `pending_review`, and an admin approves them.
- AI failure must never block capture: if the primary provider fails, try the fallback provider, then use the raw text.
- Every edit writes a `quote_revisions` snapshot. Only the submitter and admins can edit (`QuotePolicy`).
- Quotes are allowed to be unfair by design. **Opting out of tracking never hides or blocks quotes.**

## Stats and privacy

- Stats store **counts and sessions only**. Never store message content for stats.
- Opt-out is checked **twice**: in the bot before an event is queued, and again in the job. Opting out deletes all existing `stat_*` rows for that member (`PurgeMemberStats`). Opting back in only counts new activity.
- Stats writes are idempotent upserts keyed by their time bucket.

## Access

- Login is Discord OAuth only (Socialite, scopes `identify` and `guilds.members.read`). There are no passwords, no email and no registration. Fortify is not installed, so don't add it back.
- Every access decision goes through `App\Access\GuildAccess` (`allows()`, `isAdmin()`). Don't compare role IDs anywhere else. Use the `admin` gate (`Gate::allows('admin')`, `@can('admin')`) for admin-only features.
- Allowed means in the guild **and** (a role in `DISCORD_ALLOWED_ROLE_IDS`, **or** an admin role, **or** the allowed list is empty). Admin means in the guild with a role in `DISCORD_ADMIN_ROLE_IDS`.
- Every app route uses `['auth', 'guild.access']`. The middleware re-checks the stored member row on each request and logs out anyone who has lost access.
- Discord IDs (snowflakes) are **strings** everywhere: DB, PHP, TS.
- Members without access are still stored in `discord_members`. Only allowed members get a `users` row.
- The frontend user is the shared prop `auth.user` (`{id, discord_id, name, avatar_url, is_admin}`). Never send `discord_members` or `users` models straight to the frontend.

=== .ai/typefinder rules ===

# friendly-friend: TypeScript types (Typefinder and Wayfinder)

- **Never hand-write TypeScript types for backend data.** Typefinder generates them into `resources/js/typefinder/`, covering models, enums (`as const` objects plus unions), form requests, API resources, Inertia page props and broadcast events. Import with `import type { … } from '@/typefinder'` (or a category barrel such as `@/typefinder/models`).
- The output is **committed**, and CI fails on drift. Run `vendor/bin/sail artisan typefinder:generate` after backend changes and commit the result.
- Inertia actions carry `#[TypefinderPage(component: …, props: [...])]`. Rector's `AddTypefinderPageRector` adds them when the props are a literal array, so prefer literal prop arrays in `Inertia::render()`. Replace any `'unknown'` prop type that Rector writes with the real type.
- Shared props are documented with `@typefinder-return array{…}` on `HandleInertiaRequests::share()` (kept in sync by `SyncSharedPropsShapeRector`). Write classes fully qualified in that tag.
- In pages, combine the shared props with the page's own props: `usePage<Omit<SharedPageProps, keyof PageProps[N]> & PageProps[N]>()`.
- Routes and controller actions on the frontend come from **Wayfinder** (`@/actions/...`, `@/routes/...`). Never hard-code URLs.
- Use `#[TypefinderIgnore]` only for classes that must never reach the frontend.
- The Typefinder package is maintained by the project owner. Report generator bugs or rough edges to them instead of quietly working around them.

=== .ai/workflow rules ===

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

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `vendor/bin/sail composer show --direct` to list direct dependencies with versions, or `vendor/bin/sail composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `vendor/bin/sail artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `vendor/bin/sail artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/sail bin pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

=== pentacore/laravel-typefinder/core rules ===

# Laravel Typefinder

This project uses `pentacore/laravel-typefinder` to auto-generate TypeScript type definitions from Laravel source. Generated files live under the configured `typefinder.output_path` (default: `resources/js/typefinder/`) and are kept in sync by running `vendor/bin/sail artisan typefinder:generate` — either manually, via the companion Vite plugin, or from CI.

## Config

The user can publish a config file with `vendor/bin/sail artisan vendor:publish --tag=typefinder-config`.
The values are available under the `typefinder` key.

## What gets emitted

    - **Models** (`{{$assist->appPath('Models/*')}}`) → `{output_path}/models/{Name}.d.ts`. Respects `$hidden` / `$visible`. Uses `$casts` plus DB schema. Relationships are emitted as optional fields. Each file also contains `{Name}Create` and `{Name}Update` companion types (unless `typefinder.models.emit_write_shapes` is disabled).
        - **Pivots** — auto-derived from `belongsToMany` / `morphToMany` relationships. Written into `{output_path}/models/{Name}Pivot.d.ts` alongside the models they connect.
    - **Enums** (`{{$assist->appPath('Enums/*')}}`) → `{output_path}/enums/{Name}.d.ts`. Backed enums only. Setting `enums.emit_values: true` switches the output to `.ts` files with both an `as const` object (for runtime iteration) and the matching union type.
    - **Form Requests** (`{{$assist->appPath('Http/Requests/*')}}`) → `{output_path}/requests/{Name}.d.ts`. Extracted from `rules()` with null-safe proxy recovery for rules that touch request context at runtime. Rule keys tagged with a `@query` PHPDoc tag are query-string parameters: they are emitted as `{Name}Query` in the same file instead of in the request type. The tags are also read from `parent::rules()`, helper methods and another class's rules (`new X()->rules()`, `X::createFrom($this)->rules()`, `app(X::class)->rules()`) that `rules()` returns.
    - **Resources** (`{{$assist->appPath('Http/Resources/*')}}`) → `{output_path}/resources/{Name}.d.ts`. Declared via `#[TypefinderResource]` with an explicit shape, a model extension (`Omit<Model, ...> & { ... }`), or name-convention passthrough (`{Model}Resource` → `{Model}`).
    - **Pages** (opt-in, `typefinder.inertia.enabled`, `{{$assist->appPath('Http/Controllers/*')}}`) → `{output_path}/pages.d.ts`. A single file with a `PageProps` map keyed by Inertia component name. Tag controller actions with `#[TypefinderPage(component: ..., props: [...])]`.
    - **Broadcasting** (opt-in, `typefinder.broadcasting.enabled`, `{{$assist->appPath('Events/*')}}`) → `{output_path}/broadcasting.d.ts`. Public/private/presence channel maps plus a flat `BroadcastEvents` map. Discovered from classes implementing `ShouldBroadcast`.
    - **Helpers** (always emitted) → `{output_path}/helpers.d.ts`. Generic response wrappers: `WrappedResource<T>`, `WrappedResourceCollection<T>`, `PaginatedResourceCollection<T>`, `CursorPaginatedResourceCollection<T>`, `SimplePaginatedResourceCollection<T>`, `PaginatedModel<T>`, `PaginationFields`, `ValidationErrorResponse`, `ErrorResponse`.
    - **Category barrels** → `{output_path}/{category}/index.d.ts`. Re-export every type of every file in the category, companions included (`{Name}Create` / `{Name}Update`, `{Name}Query`, extracted nested request types).
    - **Top-level barrel** → `{output_path}/index.d.ts`. Re-exports every active category.

## Custom cast types

                        
For casts you own, tag the class:

```php
use Pentacore\Typefinder\Attributes\TypefinderCast;

#[TypefinderCast('{ theme: string; notifications: boolean }')]
class SettingsCast implements CastsAttributes { /* get() / set() */ }
```

For casts you can't modify (third-party packages), register them from a service provider:

```php
use Pentacore\Typefinder\Facades\Typefinder;

Typefinder::registerCast(\Spatie\MediaLibrary\Cast::class, 'Media[]');
```

`Typefinder::registerCast()` accepts a closure as the second argument when the type depends on runtime config.

</laravel-boost-guidelines>
