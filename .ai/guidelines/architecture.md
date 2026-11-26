# friendly-friend: architecture

A private site and Discord bot for **one** Discord server of friends: quotes (captured from Discord, cleaned up by AI, voted on) and server activity stats with an opt-out. Design: `docs/superpowers/specs/2026-11-26-friendly-friend-design.md`.

## Stack

Laravel 13 on PHP 8.5, Inertia 3 + Vue 3 + TypeScript (vite-plus), PostgreSQL, Redis, Horizon, Reverb, Socialite with socialiteproviders/discord (the only login), Wayfinder (typed routes), pentacore/laravel-typefinder (typed data), DiscordPHP (bot), laravel/ai (OpenAI, Anthropic or Gemini, chosen in `.env`).

## Where code goes (each part has one job)

- `App\Discord\Gateway`: listeners in the bot process. They check the opt-out cache, build a small data object and **queue a job**. They hold **no business logic** and make no DB writes beyond the member cache.
- `App\Discord\Interactions`: slash commands, the message context menu, buttons and forms. They call actions and build replies.
- `App\Discord\DiscordClient`: an interface over the Discord REST calls we use, with a real implementation and an in-memory fake for tests. Never call Discord REST directly from elsewhere.
- `App\Quotes\Actions`: `DraftQuote`, `ConfirmQuote`, `EditQuote`, `ResolvePerson`, `RecordVote`, `RecordMatchup`. All quote state changes go through these.
- `App\Ai\QuoteNormalizer`: an interface whose implementation uses laravel/ai structured output. Tests use the fake.
- `App\Stats`: ingest jobs, rollups, `PurgeMemberStats`.
- `App\Access`: who gets in. `GuildAccess` (rules), `Actions\LoginWithDiscord`, `Actions\UpsertDiscordMember`, `Jobs\SyncGuildMembers`, `Exceptions\AccessDeniedException`. The `EnsureGuildAccess` middleware (alias `guild.access`) lives in `App\Http\Middleware`.
- `App\Discord\DiscordUserApi` (OAuth user token) and `App\Discord\DiscordBotApi` (bot token): the HTTP clients for Discord REST, returning `App\Discord\Data\*` DTOs. Fake them with `Http::fake()` in tests.
- `App\Enums`: backed enums. Typefinder emits them as `as const` objects plus union types.
- Controllers stay thin: validate (FormRequest), call an action, render Inertia.

## Runtime services

Dev (Sail, `compose.yaml`): `laravel.test`, `horizon`, `reverb`, `pgsql`, `redis`, `mailpit`.
Prod (`compose.prod.yaml`, one FrankenPHP PHP 8.5 image): `web`, `horizon`, `scheduler`, `reverb`, plus `bot` from Phase 3, `postgres` and `redis`.
Docs: `docs/architecture.md`, `docs/deployment.md`. The prod image (`Dockerfile`) builds the frontend with `TYPEFINDER_SKIP_BUILD=1`, because the types are committed and there is no DB at build time.

## Dashboards

Horizon and Telescope are open in `local`. Outside `local`, Horizon needs the `admin` gate. Telescope is never registered outside `local`.
