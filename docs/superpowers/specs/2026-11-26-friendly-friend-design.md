# friendly-friend: Design Spec

**Date:** 2026-11-26
**Status:** Approved in brainstorming, awaiting written-spec review

A private website and Discord bot for one Discord server of friends. It captures quotes from Discord (cleaned up by AI, editable before saving), shows them on a website with voting, this-or-that and top lists, and tracks server activity statistics with an opt-out.

## 1. Goals and non-goals

**Goals**

- Discord-only login, restricted to members of one configured server who have an allowed role.
- Capture quotes from Discord in four ways, normalize them with AI, and let the submitter preview and edit them before saving.
- Import past quotes from an existing channel (backfill), with an admin review queue.
- Quotes can attribute lines to anyone, including people who aren't on Discord.
- Website: quote feed, search, random, up/down voting, this-or-that (Elo), top lists, person pages, edit history.
- Statistics: message activity, voice time (including co-presence), reactions and emoji, games and presence.
- Opt-out of statistics tracking, available on the web and in Discord, which deletes past stats.
- Everything typed end to end (Typefinder and Wayfinder). AI rules and docs are updated in every commit.

**Non-goals (YAGNI)**

- Supporting more than one server.
- Storing message content for stats (counts only).
- Quote tags or categories, comments, public or anonymous access, mobile apps.
- Backfilling _statistics_ from channel history. Stats start when the bot joins, and only quotes can be imported.
- Opt-out does **not** cover quotes. People can always be quoted.

## 2. Stack

| Concern              | Choice                                                                                                                                     |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Framework            | Laravel 13 (latest), `"php": "^8.5"`                                                                                                       |
| Frontend             | Official Vue starter kit: Inertia 2, Vue 3, TypeScript, Tailwind 4, shadcn-vue                                                             |
| Types                | `pentacore/laravel-typefinder` + `@pentacore/vite-plugin-laravel-typefinder` (data types), Laravel Wayfinder (routes)                      |
| Database             | PostgreSQL                                                                                                                                 |
| Queue/cache/session  | Redis, Laravel Horizon                                                                                                                     |
| Realtime             | Laravel Reverb + Echo (vote counts, new quotes)                                                                                            |
| Auth                 | Laravel Socialite + Discord provider (`socialiteproviders/discord`)                                                                        |
| Discord gateway/REST | DiscordPHP (`team-reflex/discord-php`) inside an artisan command                                                                           |
| AI                   | Laravel AI SDK (`laravel/ai`). The provider (OpenAI, Anthropic or Gemini) and model are set in `.env`, with a fallback provider            |
| Dev env              | Laravel Sail (PHP 8.5 runtime), with Docker Desktop WSL integration turned on                                                              |
| Prod                 | Docker Compose on a home server, using a custom PHP 8.5 image (FrankenPHP)                                                                 |
| Quality              | Pest 4 (incl. browser tests), Larastan (max level), Pint, Rector (with Typefinder sets), ESLint, Prettier, vue-tsc, Telescope (local only) |
| AI rules             | Laravel Boost (`CLAUDE.md`/`AGENTS.md` + guidelines), project guidelines in `.ai/guidelines/`, Typefinder Claude skill                     |

Exact package versions are checked at install time against current docs (via context7). Anything that turns out to be unavailable for Laravel 13 or PHP 8.5 is flagged then, not worked around silently.

## 3. Architecture

```
Discord gateway ──► bot (php artisan bot:run, DiscordPHP)
                       │  turns each event into a small data object and queues a job; no business logic
                       ▼
                  Redis ◄──► Horizon workers ──► PostgreSQL
                                   │──► AI provider (via laravel/ai)
                                   └──► Discord REST (interaction replies, posts)
Browser (Vue/Inertia) ──► web (FrankenPHP) ──► PostgreSQL / Redis
                       ◄── Reverb (WebSockets)
```

**Compose services (prod):** `web`, `bot`, `horizon`, `scheduler`, `reverb`, `postgres`, `redis`. The five app services share one image and run different commands. Sail runs the equivalent set in development.

**Code organization** (each part has one job and its own tests):

- `App\Discord\Gateway`: event listeners. They check the opt-out cache, build a small data object and queue a job. Nothing else.
- `App\Discord\Interactions`: slash commands, message context menu, buttons, forms. They call actions and build replies.
- `App\Discord\DiscordClient`: an interface over the REST calls we use, with a real implementation and an in-memory fake for tests.
- `App\Quotes\Actions`: `DraftQuote`, `ConfirmQuote`, `EditQuote`, `ResolvePerson`, `RecordVote`, `RecordMatchup`.
- `App\Ai\QuoteNormalizer`: an interface whose implementation uses the AI SDK with structured output. It returns `{lines: [{speaker_name, speaker_discord_id?, text}], context?, said_at?, confidence}`.
- `App\Stats`: ingest jobs, rollup jobs, `PurgeMemberStats`.
- `App\Access`: `VerifyGuildAccess` (login and middleware) and `SyncGuildMembers` (scheduled).

## 4. Data model

Configured in `.env`: `DISCORD_GUILD_ID`, `DISCORD_ALLOWED_ROLE_IDS`, `DISCORD_ADMIN_ROLE_IDS`, `DISCORD_QUOTES_CHANNEL_ID`.

**Identity**

- `discord_members`: id, discord_id (unique), username, global_name, guild_nick, avatar, role_ids (jsonb), in_guild (bool), tracking_opted_out_at (nullable), timestamps. Synced by the bot for **every** member.
- `users`: id, discord_member_id (FK, unique), remember_token, last_access_check_at, timestamps. Admin status comes from `role_ids` ∩ admin roles.
- `people`: id, display_name, slug (unique), discord_member_id (nullable FK, unique), timestamps. An outsider is a person with no `discord_member_id`.
- `person_aliases`: id, person_id, alias (case-insensitive unique).

**Quotes**

- `quotes`: id, status (`QuoteStatus`: draft, pending_review, published, removed), context (nullable), said_at (nullable), said_at_precision (`exact`, `day`, `approximate`), source (`QuoteSource`: reply_mention, context_menu, slash_command, mention_freetext, channel_import), source_message_id (nullable, unique), source_message_url (nullable), import_id (nullable FK), channel_id (nullable), submitted_by_member_id (FK), raw_input (text), ai_output (jsonb, nullable), score (int, cached up minus down), elo (int, default 1000), matchup_count, published_at, timestamps, plus a Postgres full-text `tsvector` generated column over the lines and context.
- `quote_lines`: id, quote_id, person_id, position, text.
- `quote_revisions`: id, quote_id, editor_member_id, snapshot (jsonb of the lines, context and said_at), created_at.
- `quote_votes`: quote_id, user_id, value (+1/-1), unique (quote_id, user_id).
- `quote_matchups`: id, user_id, quote_a_id, quote_b_id, winner_quote_id, created_at. Elo uses K=32.

- `quote_imports`: id, channel_id, requested_by_member_id, status (queued, running, done, failed), before_message_id cursor, messages_scanned, quotes_found, started_at, finished_at.

**Stats** (keyed by `discord_member_id`, so members who never log in are counted too)

- `stat_messages`: member_id, channel_id, hour (timestamptz, truncated), count. Unique (member, channel, hour). Updated by upsert.
- `stat_voice_sessions`: id, member_id, channel_id, started_at, ended_at (nullable).
- `stat_reactions`: reactor_member_id, target_member_id (nullable), emoji (unicode or custom id:name), day, count. Unique on all but count.
- `stat_activity_sessions`: id, member_id, activity_name, activity_type, started_at, ended_at (nullable).
- `stat_daily_*` rollups, written nightly, for the dashboards. Voice co-presence ("hours together") is calculated from overlapping `stat_voice_sessions` in the nightly rollup.

## 5. Quote capture (Discord)

All four input methods produce a **draft quote**, then a **private preview**, then the submitter **confirms**.

| Method            | Trigger                                                                 | Speaker/text source                                                                                                                                                                    |
| ----------------- | ----------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Reply + mention   | Reply to a message and @bot                                             | The replied-to message's author and content are known for certain. The AI only normalizes formatting (resolves `<@id>` mentions to names, joins multi-line text) and suggests context. |
| Context menu      | Right-click a message → Apps → "Save quote"                             | Same as above                                                                                                                                                                          |
| Slash             | `/quote text:<str> who:<user or free text>? context:<str>? when:<str>?` | The given values. The AI normalizes them and parses `when`.                                                                                                                            |
| Free-text mention | `@bot Martin said "x" to his uncle yesterday`                           | The AI extracts speakers, lines, context and said_at                                                                                                                                   |

1. The interaction or listener calls `DraftQuote`, which saves a `draft` quote immediately (so nothing is lost) and queues the normalization job.
2. The normalization job calls `QuoteNormalizer` (timeout 15 s, primary then fallback provider). On failure or low confidence it uses the raw text as a single line.
3. `ResolvePerson` for each speaker checks, in order: Discord ID → alias or display name (case-insensitive) → Postgres trigram similarity ≥ 0.6 → otherwise proposes a **new outside person**.
4. The bot sends a private reply (or a direct message, for the mention-based methods) with the formatted draft, how each speaker was resolved, and the buttons **Save**, **Edit** and **Cancel**.
5. **Edit** opens a Discord form with fields for the lines (one `Name: text` per line), context and when. Submitting re-parses it without AI (plain `Name:` prefix parsing plus `ResolvePerson`) and shows the preview again.
6. **Save** calls `ConfirmQuote`, which sets the status to `published`, writes the first `quote_revisions` snapshot, broadcasts `QuotePublished` (Reverb), and posts to `DISCORD_QUOTES_CHANNEL_ID`.
7. Drafts not confirmed within 15 minutes are deleted by a scheduled job.

`/quote random [person]` posts a random published quote in the channel.

### 5.1 Channel import (backfill)

For channels that already hold quotes, e.g. an old `#quotes` channel.

- Started by an admin with `/quote import channel:<#channel>` or `php artisan quotes:import {channel_id}`, or from the admin page. This creates a `quote_imports` row.
- `ImportChannelQuotes` pages backwards through the channel history over REST (100 messages per page, cursor saved after each page so the import can be resumed and safely re-run). Bot messages and messages that were already imported (`source_message_id` unique) are skipped.
- Messages are sent to `QuoteNormalizer::classifyBatch()` in batches of about 20. For each message it returns: is it a quote? If so, the speakers, lines, context and said_at. A reply to another message counts as quoting it. If the AI finds no date in the text, the message's own timestamp is used.
- Each quote it finds becomes a quote with status `pending_review`. Speakers are resolved by `ResolvePerson`, except that unmatched names are **not** created automatically. They are flagged for the reviewer instead.
- **Admin review queue** `/admin/imports/{id}`: a list of found quotes next to their original message, with buttons to approve, edit, reject, and approve all high-confidence ones. Approving runs `ConfirmQuote` with the original message author as submitter and without posting to the quotes channel. The quotes appear on the site with their original dates.
- Progress (messages scanned, quotes found) is broadcast to the admin page over Reverb.

## 6. Website

All routes require authentication and pass the `guild.access` middleware.

- **Feed** `/quotes`: paginated, newest first, filter by person, full-text search.
- **Quote** `/quotes/{id}`: permanent link, votes, edit history, edit button (submitter or admin).
- **Random** `/quotes/random`: redirects to a random quote, with a shuffle button.
- **Voting**: up or down vote on each quote. `RecordVote` updates `score`, and `QuoteScoreChanged` is broadcast.
- **This or that** `/this-or-that`: two random published quotes (weighted towards fewer matchups). Picking one runs `RecordMatchup`, which updates Elo, then the next pair loads.
- **Top lists** `/top`: all-time by score, this month by score, by Elo, most quoted people, top submitters.
- **People** `/people/{slug}`: their quotes, plus their stats (hidden if they've opted out).
- **Stats** `/stats`: activity heatmap (weekday × hour), message leaderboard, voice leaderboard and co-presence pairs, top emoji and reaction pairs, most-played games. Opted-out members are absent because their data is deleted.
- **Settings** `/settings/privacy`: opt out or back in to tracking.
- **Admin** `/admin`: channel imports and their review queues (§5.1), merge people (moves lines and aliases), manage aliases, link a person to a Discord member, remove quotes, bot status (heartbeat).

**Edit rights:** the submitter and admins. Every edit writes a `quote_revisions` snapshot. Authorization lives in `QuotePolicy`.

All Inertia controller actions carry `#[TypefinderPage]` (kept current by Rector's `AddTypefinderPageRector`). Broadcast events are typed through Typefinder's broadcasting category.

## 7. Access control

- OAuth scopes: `identify`, `guilds.members.read`.
- At login: fetch the member record for `DISCORD_GUILD_ID`. Access is denied unless the user is a member and has at least one role in `DISCORD_ALLOWED_ROLE_IDS`. Their `discord_member` and `user` rows are created or updated.
- The `guild.access` middleware rejects users with `in_guild = false` or no allowed role, using the bot-synced `discord_members` data.
- `SyncGuildMembers` runs every 3 hours (and the bot updates members in real time on member join, update and leave). _Implementation note (Phase 2):_ sessions live in Redis, so they aren't deleted. Instead `guild.access` re-checks the stored member row on every request and logs out anyone who lost access. Admin roles always count as allowed, and an empty allowed list means every guild member may log in.

## 8. Statistics and opt-out

**Gateway intents:** GUILDS, GUILD_MEMBERS, GUILD_MESSAGES, MESSAGE_CONTENT (for mention-based capture only, never stored for stats), GUILD_MESSAGE_REACTIONS, GUILD_VOICE_STATES, GUILD_PRESENCES.

| Event                    | Job                | Write                                                                |
| ------------------------ | ------------------ | -------------------------------------------------------------------- |
| MESSAGE_CREATE (non-bot) | `IngestMessage`    | upsert `stat_messages` +1                                            |
| MESSAGE_REACTION_ADD     | `IngestReaction`   | upsert `stat_reactions` +1 (target = message author when known)      |
| VOICE_STATE_UPDATE       | `IngestVoiceState` | close the open session, open a new one if the member joined or moved |
| PRESENCE_UPDATE          | `IngestPresence`   | close or open `stat_activity_sessions` for playing-type activities   |

- On bot startup or resume, sessions still open are closed at the last known time, then new sessions are opened from the current voice and presence state.
- **Opt-out** (web or `/privacy optout|optin|status`): sets `tracking_opted_out_at`, updates the cached opted-out set in Redis, and queues `PurgeMemberStats`, which deletes every `stat_*` row for that member and rebuilds the affected rollups. The opt-out is checked twice: the bot drops the event before queuing it, and the job checks again. Opting back in clears the flag, and only new activity is counted.

## 9. Error handling

- Gateway listeners catch and log every exception, and a single bad event never stops the loop. Docker restarts the bot (`restart: unless-stopped`) and DiscordPHP resumes the session.
- The bot writes a heartbeat key to Redis every 30 s, and the admin page shows whether the bot is online.
- Jobs retry 3 times with exponential backoff. A Discord 429 response makes the job release itself for `retry_after`.
- AI failure falls back to the second provider, then to the raw text, so quote capture never fails because of the AI.
- Stats writes are idempotent upserts keyed by their bucket.

## 10. Testing and CI

- **Pest feature tests** for every action and job, with `QuoteNormalizer` and `DiscordClient` faked and the AI SDK's fake responses.
- **Interaction tests**: recorded interaction payload fixtures are dispatched to the handlers, and the replies built are checked.
- **Pest 4 browser tests**: login gate, voting, this or that, opt-out.
- **CI** (GitHub Actions or GitLab CI, depending on the remote): Pint `--test`, Rector `--dry-run`, Larastan at max level, `php artisan migrate` then `typefinder:generate --check --strict` (generated types committed, `gitignore_generated: false`), ESLint, `vue-tsc --noEmit`, Pest, and the rules-and-docs check (a warning when `app/`, `database/` or `routes/` change with no change under `docs/` or `.ai/`).

## 11. AI rules and docs

- Laravel Boost is installed and generates `CLAUDE.md` and `AGENTS.md` and the package guidelines.
- Project guidelines in `.ai/guidelines/`: `architecture.md`, `discord-bot.md`, `quotes.md`, `stats-and-privacy.md`, `typefinder.md`, `workflow.md`. Boost merges them into the generated rules.
- The Typefinder Claude skill is published to `.claude/skills/`.
- Feature docs in `docs/` (`setup.md`, `discord-app.md`, `deployment.md`, `features/*.md`).
- **Rule:** every commit that changes conventions, commands, the schema or a feature also updates the matching guideline and doc, and re-runs `boost:update` and `typefinder:generate` where relevant. This is stated in `.ai/guidelines/workflow.md`.

## 12. Build phases

Each phase ends with passing CI, a commit, and rules and docs updated.

1. **Foundation**: scaffold the Laravel 13 Vue starter kit (into a temp directory, then move it here, since this directory already holds `docs/`), set up Sail with PHP 8.5, Postgres and Redis, install all the tooling, Typefinder (Inertia and broadcasting turned on, Rector sets), Boost and guidelines, CI, the production Dockerfile and compose file.
2. **Access**: Socialite Discord login, the guild and role gate, `discord_members`/`users` and the access middleware. Drop the starter kit's email/password auth.
3. **Bot core**: `bot:run`, the event → job pipeline, member sync, heartbeat, `DiscordClient` and its fake.
4. **Quotes in Discord**: `people`/aliases, quotes schema, `QuoteNormalizer`, the four input methods, preview, edit and save, the quotes channel post, `/quote random`, channel import job.
5. **Quotes on the web**: feed, search, quote page and edit history, voting with Reverb, this or that, top lists, people pages, admin (including the import review queue).
6. **Stats**: ingest jobs, sessions, rollups, dashboards, opt-out on the web and in Discord, purge.

## 13. Owner setup (not code)

- Discord Developer Portal: create the application and bot, set the OAuth redirect URI, turn on the privileged intents **Server Members**, **Presence** and **Message Content**, and invite the bot with the `applications.commands` and `bot` scopes. It needs the View Channel, Read Message History, Send Messages and Embed Links permissions.
- At least one AI provider API key (two if you want the fallback).
- Turn on Docker Desktop WSL integration for this distro.
