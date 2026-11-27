# Phase 6: Server Stats with Opt-out Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** The bot counts messages, reactions, voice time and games per server and member, never message content. The site shows the activity as dashboards, per server or combined, and on people pages. Anyone can opt out on the website or with `/privacy` in Discord. Opting out deletes everything already stored about them.

**Architecture:**

- **Ingest:** `GatewayEventRouter` drops events from bots and from opted-out members (a cached set in Redis, `OptedOutMembers`, read without touching the database), then queues one small job per event. The jobs are `IngestMessage`, `IngestReaction`, `IngestVoiceState` and `IngestPresence`. Each job checks again against the database (`discord_users.tracking_opted_out_at`) and ignores disabled servers.
- **Storage:** counters are upserts into hour or day buckets (`stat_messages`, `stat_reactions`). Voice and game time are sessions with `ended_at` null while open (`stat_voice_sessions`, `stat_activity_sessions`).
- **Reactions:** the reaction target comes from `message_author_id`. A target who has opted out is stored as null.
- **Reconnect:** a fresh gateway session (`GUILD_CREATE`) queues `SeedGuildSessions` with the server's current voice states and games. Sessions that still match are kept, the rest are closed now, and missing ones are opened. On RESUME, Discord replays missed events, so nothing extra is needed.
- **Rollups:** `RollUpStats(day)` rebuilds `stat_daily_voice`, `stat_daily_activity` and `stat_daily_voice_pairs` (co-presence, two members in the same channel at the same time) for one day in `config('stats.timezone')`. It runs every hour for today and just after midnight for yesterday. Each run deletes and rebuilds the whole day, so it is idempotent.
- **Opt-out:** the actions `OptOutOfStats` and `OptIntoStats` set the flag and update the cached set. Opting out also queues `PurgeMemberStats`, which deletes every `stat_*` row about the member, including pairs and reaction targets. Rollups are per member, so the purge deletes them directly instead of rebuilding them.
- **Web:** `/stats` has these filters:
  - server: one or all the user can see
  - period: 7, 30 or 365 days

  It shows a weekday × hour heatmap, message, voice and reaction leaderboards, co-presence pairs, top emoji and most played games. `/people/{person}` shows that person's stats unless they have opted out. `/settings/privacy` turns tracking off or on.
- **Discord:** `/privacy status|optout|optin`, answered privately. It is registered next to `/quote` through `BotCommands`.

## Slices (each a commit, with tests, rules, docs and ROADMAP)

1. **Schema, models and opt-out core:**
   - migration
   - models and factories
   - `config/stats.php`
   - `OptedOutMembers`, `OptOutOfStats`, `OptIntoStats`, `PurgeMemberStats`
2. **Ingest:**
   - the four ingest jobs and `SeedGuildSessions`
   - router routes for `MESSAGE_CREATE`, `MESSAGE_REACTION_ADD`, `VOICE_STATE_UPDATE`, `PRESENCE_UPDATE` and `GUILD_CREATE` seeding
   - gateway fixtures
3. **Rollups:** `RollUpStats` with clipping at day boundaries, co-presence pairs, and the schedule.
4. **Privacy:**
   - the `/settings/privacy` page
   - `/privacy` command handling, its ack and its registration
5. **Dashboards:**
   - the `StatsQuery` read model, `/stats` and the sidebar link
   - stats on people pages
   - browser tests in light and dark mode
