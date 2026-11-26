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
