---
paths:
    - '**'
---

# General (the whole project)

## What this is

A private website and Discord bot for a group of friends across **one or more Discord servers**:

- quotes captured from Discord, tidied up by AI, browsed, voted on and ranked on the site
- server activity stats (messages, reactions, voice, games) with an opt-out
- event planning: RSVP, food, transport, costs, Discord announcements and reminders

The spec is `docs/superpowers/specs/2026-11-26-friendly-friend-design.md`, phase plans are in `docs/superpowers/plans/`, the backlog of open items is `ROADMAP.md`, and what's done is in `COMPLETED.md`.

## Stack

Laravel 13 on PHP 8.5 (Sail), PostgreSQL, Redis, Horizon, Reverb, Inertia 3 + Vue 3 + TypeScript (vite-plus, Tailwind 4, shadcn-vue), Socialite with socialiteproviders/discord (the only login), Wayfinder (typed routes), pentacore/laravel-typefinder (typed data, maintained by the project owner), DiscordPHP (bot transport only) and laravel/ai (OpenAI, Anthropic or Gemini with failover; Jev, TypeSafe's decision model, optional through OpenRouter).

## Keeping rules current

- These rule files are the project's conventions and design decisions. **Every commit that introduces or changes a convention, a design decision or a trap updates the matching rule file in the same commit.** If a decision spans the project, put it here; if it's about one area, put it in that area's file. Create a new rule file with a `paths` frontmatter for a new area. How to write and file rules (narrowest glob, owner's decisions, skills for procedures) is in `ai-rules.md`.
- Bugs, follow-ups, upstream issues and feature ideas go in `ROADMAP.md`, also in the same commit, under the group that matches what the item needs next: Decisions (the owner chooses), Brainstorms (design first), Ready to pick up, Setup outside the code, Held dependency updates or Upstream reports. An idea the owner drops goes to Not planned with its reason, so it isn't proposed again. `ROADMAP.md` holds only open items: when one is done (or a bug is found and fixed in the same change), it goes to `COMPLETED.md` in that commit, and a fixed bug's lesson becomes a rule here.

## Words

`GLOSSARY.md` names the domain terms (server, person, organiser, settle up…) with their code names, and the words to avoid. Use its words in code, UI copy, rules, docs and commits, and update it in the same commit when a change adds or renames a term.

## Worktrees

Work that runs alongside other work gets its own git worktree and its own test database (`testing_<name>`, passed as `DB_DATABASE`), never the main checkout's or another worktree's. Setup, running tests and cleanup are in the `working-in-a-worktree` skill.

## Shape of the app (each part has one job)

| Namespace      | Job                                                                                                             |
| -------------- | --------------------------------------------------------------------------------------------------------------- |
| `App\Access`   | Who gets in, server settings (`GuildAccess`, login, member sync)                                                |
| `App\Discord`  | Talking to Discord: gateway process, REST client, interactions, DTOs                                            |
| `App\Quotes`   | Quote domain: actions, Discord flows, jobs, imports, rules                                                      |
| `App\Planning` | Event planning: actions, `EventRules`, Discord flows and jobs (`App\Models\Event`)                              |
| `App\Ai`       | Quote normalizer and detector, laravel/ai agents                                                                |
| `App\Stats`    | Stats ingest, rollups, opt-out and purge, the `StatsQuery` read model                                           |
| `App\Http`     | Thin controllers (validate with a FormRequest, call an action, render Inertia), resources, requests, middleware |
| `App\Enums`    | Backed enums, emitted by Typefinder as `as const` objects plus unions                                           |

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
- Parallel tests ran Postgres out of lock slots (`out of shared memory`) once the schema grew. Sail's `pgsql` runs with `max_locks_per_transaction=256` (`compose.yaml`). Recreate the container after pulling (`vendor/bin/sail up -d pgsql`).
- Rector's local cache only re-checks changed files, so a return type changed in one class can leave a needed fix in an unchanged caller unseen until CI (which has no cache). After changing a signature, run `vendor/bin/sail bin rector process --dry-run --clear-cache`.
- Don't trust Rector blindly with arithmetic on casts: it once changed `(int) ((int) $x >> 22) % 6` into different precedence. Keep a test on anything bit-twiddly.

## Limits are owner settings

A limit, window, threshold or count an owner could reasonably want to tune (reminder times, draft expiry, top-list sizes) is an `App\Enums\AppSetting` case with a default, bounds, a label and help text, read through `App\Access\AppSettings` (which falls back to the default and clamps), and shown on the owners' Settings page automatically. It is not a hard-coded constant or a `config/` value. Discord API codes and limits, page sizes and algorithm constants (the Elo K, the fuzzy-match threshold) stay constants. (owner's rule, 2026-11-27)

## Product rules

- **Dark mode is required**: every page must be correct in light and dark (see `frontend.md`).
- Quotes are allowed to be unfair by design. Opting out of stats never hides or blocks quotes.
- Nothing the bot posts may ping people (`allowed_mentions` none), unless it's a deliberate reply to the person who asked.
