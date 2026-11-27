---
paths:
    - 'app/Stats/**'
---

# Stats and privacy

## What is stored

- Stats store **counts and sessions only**. Never store message content.
- Everything is keyed by `guild_id` + `discord_user_id` (internal IDs), so members who never log in count too, and every stat works per server or combined. `IngestMessage` records unknown authors through `RecordDiscordUser`; the other jobs skip members we don't know yet (member sync adds them).
- Counters (`stat_messages` per hour in UTC, `stat_reactions` per day in `config('stats.timezone')`) are **idempotent upserts** on their bucket (`count = count + 1`). `stat_reactions` has a `nulls not distinct` unique index, because `target_discord_user_id` is null when unknown.
- Voice and games are **sessions** with `ended_at` null while open. A partial unique index allows one open voice session per member and server, and one per game. `RecordVoiceState` and `RecordActivities` bring sessions in line with the current state (keep matches, close the rest, open what's missing); after a restart, closed sessions end at the previous heartbeat (`closedAt`), not at reconnect time. Every ingest path and the reconnect seed go through them.
- Only `config('stats.activity_types')` count as games (0 = Playing). The router filters them, so presence jobs carry only names.

## Opt-out (checked twice)

1. The **router** drops events whose member is in `OptedOutMembers`, a cached list read without the database. A cold cache means "nobody", which is safe because of check 2. `bot:run` refreshes it at startup, and the opt-out actions refresh it on every change.
2. Every **job** checks `discord_users.tracking_opted_out_at` again (`Stats\Tracking`). Reaction targets who opted out are stored as null.

- Opting out (`OptOutOfStats`) sets the flag and queues `PurgeMemberStats`. The purge deletes every row about them: counters, sessions, rollups, pairs, their reactions, and their name as a reaction target (those rows fold into the reactor's null-target row). Opting back in (`OptIntoStats`) only counts new activity.
- Opting out never touches quotes.

## Rollups

- `RollUpStats` rebuilds one **local day** (`stats.timezone`) of `stat_daily_voice`, `stat_daily_activity` and `stat_daily_voice_pairs` from the sessions: delete the day, then insert. Sessions are clipped to the day, and open ones count until now. It runs hourly for today and at 00:15 for yesterday. `stats:rollup --days=N` backfills.
- Pairs are stored once, with `first_discord_user_id < second_discord_user_id`, for members in the **same channel** at the same time.
- Messages and reactions are already bucketed, so dashboards read them directly. Only time-based stats need rollups.

## Reading stats

- Pages read stats only through `Stats\StatsQuery`, built with the viewer's `AccessSnapshot::guildIds` (or one of them) and a period in local days. Never query `stat_*` tables from controllers.
- Leaderboards return `{ member, total }` and pairs `{ first, second, total }`, where `member` is `{ id, name, avatar_url, person_slug }`. The unit of `total` depends on the board (count or seconds), and the page formats it.
- Person pages show stats only for a linked Discord user who hasn't opted out (`stats: null` otherwise).
