# Server stats

The bot counts activity in every **enabled** server: messages, reactions, voice time and games. It never stores what anyone wrote. Members who never log in are counted too. Anyone can opt out, which also deletes what was already stored about them.

## What is collected

| Stat      | Table                    | Granularity                                                        |
| --------- | ------------------------ | ------------------------------------------------------------------ |
| Messages  | `stat_messages`          | count per member, channel and hour (UTC)                           |
| Reactions | `stat_reactions`         | count per reactor, emoji, day, and whose message it was (if known) |
| Voice     | `stat_voice_sessions`    | one row per stay in a voice channel (`ended_at` null while there)  |
| Games     | `stat_activity_sessions` | one row per game played ("Playing …" activities only, in a server) |

Not counted: bots, webhooks, system notices (joins, pins, boosts), DMs, Spotify, custom statuses, and servers that aren't enabled.

Days (reactions, rollups, the heatmap) use `STATS_TIMEZONE` (default `Europe/Stockholm`).

## Flow

1. The bot (`GatewayEventRouter`) turns `MESSAGE_CREATE`, `MESSAGE_REACTION_ADD`, `VOICE_STATE_UPDATE` and `PRESENCE_UPDATE` into small queued jobs in `App\Stats\Jobs`. Events from members who opted out are dropped here (see [bot.md](bot.md)).
2. The jobs check the server is enabled and the member hasn't opted out (again, against the database), then upsert a counter or move a session.
3. When the bot starts a fresh gateway session, `GUILD_CREATE` carries who is in voice and who is playing what. `SeedGuildSessions` keeps sessions that still match, closes the rest and opens what's missing. Events missed while the bot was down are lost. Sessions that no longer match end at the bot's last heartbeat before the restart (`BotHeartbeat::downSince()`, only within 10 minutes of a start); people still in the same channel or game afterwards are assumed to have stayed.

- **Dashboard:** a "This week" card (messages, voice time, people, the chattiest person) linking to `/stats` for the last 7 days. Hidden when nothing was counted.

## Rollups

`RollUpStats` turns sessions into daily totals:

- `stat_daily_voice`: seconds in voice per member
- `stat_daily_activity`: seconds per member and game
- `stat_daily_voice_pairs`: seconds two members spent in the **same channel** at the same time ("time together")

It rebuilds a whole day each run, so it's safe to repeat. It runs every hour for today and at 00:15 for yesterday. To rebuild older days (for example after changing `STATS_TIMEZONE`):

```bash
vendor/bin/sail artisan stats:rollup --days=30
```

## On the website

- **`/stats`** (sidebar → Stats): one server or all you can see, over a week, a month or a year.
    - a one-line summary
    - a weekday × hour heatmap of messages (in `STATS_TIMEZONE`)
    - boards: most messages, most time in voice, time together, busiest channels (named from the server's cached channel list; channels the bot can't see show as "a hidden channel"), most played, favourite reactions, who reacts to whom, most reactions given
- **Person pages** (`/people/{slug}`): the last 30 days for that person, if they're linked to a Discord user and haven't opted out. Shows a summary, their own heatmap, who they spend the most voice time with, what they play, and the emoji they react with.

Everything is read through `App\Stats\StatsQuery`, limited to the servers the viewer can see.

## Opting out

Two ways, both available to everyone, with or without access to the website:

- **Website:** Settings → Privacy (`/settings/privacy`) shows whether stats are on, with a confirmation before turning them off.
- **Discord:** `/privacy status`, `/privacy optout`, `/privacy optin`. Answered privately (`Stats\Jobs\HandlePrivacyCommand`). Registered next to `/quote` in every enabled server when the bot connects (or right away with `vendor/bin/sail artisan discord:register-commands`).

Opting out sets `discord_users.tracking_opted_out_at`, refreshes the cached opted-out list the bot reads, and queues `PurgeMemberStats`, which deletes:

- their messages, reactions, sessions and rollups
- every "time together" pair they're part of
- their name on other people's reactions (those reactions stay, without a target)

Opting back in only counts new activity. Quotes are never affected.
