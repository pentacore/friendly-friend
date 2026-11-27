---
paths:
    - '**'
---

# General (the whole project)

## What this is

A private website and Discord bot for a group of friends across **one or more Discord servers**:

- quotes captured from Discord, tidied up by AI, browsed, voted on and ranked on the site
- server activity stats with an opt-out (Phase 6)
- event planning: RSVP, food, transport, costs (Phase 7)

The spec is `docs/superpowers/specs/2026-11-26-friendly-friend-design.md`, phase plans are in `docs/superpowers/plans/`, and the backlog is `ROADMAP.md`.

## Stack

Laravel 13 on PHP 8.5 (Sail), PostgreSQL, Redis, Horizon, Reverb, Inertia 3 + Vue 3 + TypeScript (vite-plus, Tailwind 4, shadcn-vue), Socialite with socialiteproviders/discord (the only login), Wayfinder (typed routes), pentacore/laravel-typefinder (typed data, maintained by the project owner), DiscordPHP (bot transport only) and laravel/ai (OpenAI, Anthropic or Gemini with failover; Jev/TypeSafe optional for classification).

## Keeping rules current

- These rule files are the project's conventions and design decisions. **Every commit that introduces or changes a convention, a design decision or a trap updates the matching rule file in the same commit.** If a decision spans the project, put it here; if it's about one area, put it in that area's file. Create a new rule file with a `paths` frontmatter for a new area.
- After adding or renaming rule files, run `vendor/bin/sail artisan boost:index-rules` to regenerate `index.md`. Never edit `index.md` by hand.
- Bugs, follow-ups, upstream issues and feature ideas go in `ROADMAP.md`, also in the same commit. Fixed bugs stay listed there, and their lesson becomes a rule here.

## Shape of the app (each part has one job)

| Namespace     | Job                                                                                                             |
| ------------- | --------------------------------------------------------------------------------------------------------------- |
| `App\Access`  | Who gets in, server settings (`GuildAccess`, login, member sync)                                                |
| `App\Discord` | Talking to Discord: gateway process, REST client, interactions, DTOs                                            |
| `App\Quotes`  | Quote domain: actions, Discord flows, jobs, imports, rules                                                      |
| `App\Ai`      | Quote normalizer and detector, laravel/ai agents                                                                |
| `App\Stats`   | Stats ingest, rollups, opt-out purge (Phase 6)                                                                  |
| `App\Http`    | Thin controllers (validate with a FormRequest, call an action, render Inertia), resources, requests, middleware |
| `App\Enums`   | Backed enums, emitted by Typefinder as `as const` objects plus unions                                           |

- State changes go through **actions** (`final readonly`, one `handle()`). Controllers, jobs and interaction handlers call actions and don't reimplement them.
- There's no events/listeners layer, and no services or repositories. Work is chained by dispatching jobs. **Exception:** `ShouldBroadcast` events exist only to push live updates to browsers over Reverb. They carry no logic and nothing listens to them server-side.

## Identity and servers

- Data is **one shared pool tagged by server**: quotes carry `guild_id`, and stats are keyed by `guild_id` + `discord_user_id`. Never build per-server silos.
- `discord_users` is the person (one row across all servers), `guild_members` is one server membership (nick, roles, `in_guild`), and `users` are people who have logged in.
- `guilds` are registered by the bot and start **disabled**. Jobs ignore disabled servers.
- `people` are anyone who can be quoted. `discord_user_id` is nullable, because people outside the servers can be quoted too.

## Traps that already bit us

- Discord IDs (snowflakes) are **strings** everywhere (DB, PHP, TS). PHP turns numeric-looking array **keys** into ints, so cast with `(string)` when IDs come back out of keys (`array_flip()`, `foreach ($map as $id => …)`).
- Postgres `jsonb` reorders object keys. Never rely on key order, and compare stored JSON with `toEqual`.
- The Redis cache returns numbers as strings. Store strings (ISO dates) and parse them on read.
- `env()` is only allowed in `config/*.php`, because config is cached in production. Read `config()` everywhere else.
- Reverb has two endpoints: `REVERB_*` (where the app publishes: `reverb:8080` inside Docker, never `localhost`) and `VITE_REVERB_*` (where browsers connect, compiled in at build time).
- The bot and Horizon are long-running processes. Restart them after code changes (`vendor/bin/sail restart bot horizon`).
- Don't trust Rector blindly with arithmetic on casts: it once changed `(int) ((int) $x >> 22) % 6` into different precedence. Keep a test on anything bit-twiddly.

## Product rules

- **Dark mode is required**: every page must be correct in light and dark (see `frontend.md`).
- Quotes are allowed to be unfair by design. Opting out of stats never hides or blocks quotes.
- Nothing the bot posts may ping people (`allowed_mentions` none), unless it's a deliberate reply to the person who asked.
