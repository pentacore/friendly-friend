# Discord bot

`php artisan bot:run` holds the Discord gateway (websocket) connection. It's deliberately thin:

```
Discord gateway ──raw dispatch──► RunBot ──► GatewayEventRouter::route($type, $payload)
                                                │  checks the guild, builds a DTO
                                                ▼
                                           queued job ──► Horizon worker (DB, REST, AI)
```

- **No business logic or DB access in the bot process.** `App\Discord\Gateway\GatewayEventRouter` is plain PHP: it validates the payload, maps it to a DTO and dispatches a job. It's unit-tested with JSON fixtures in `tests/Fixtures/gateway/`.
- The bot can be in **several servers**. The router forwards events from all of them, and the jobs ignore servers that aren't enabled on [`/admin/guilds`](admin.md).
- DiscordPHP is used only as the transport (`loadAllMembers`, `storeMessages` and `retrieveBans` are off). The database is the source of truth.
- One bad event never stops the loop: routing errors are caught and logged.

## Events handled

| Gateway event                               | Job                                    | Effect                                                                                                                                                                     |
| ------------------------------------------- | -------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GUILD_CREATE` (joined, or connected)       | `Discord\Jobs\RegisterGuild`           | Server registered or updated (name, icon, roles, `bot_present`). New servers start **disabled**. Enabled ones get a `SyncGuildMembers`. Ignored if `unavailable` (outage). |
| `GUILD_UPDATE`                              | `Discord\Jobs\RegisterGuild` (no sync) | Name, icon and roles updated                                                                                                                                               |
| `GUILD_ROLE_CREATE` / `_UPDATE` / `_DELETE` | `Discord\Jobs\RefreshGuild`            | Roles re-read over REST (unique per server, so bursts fetch once)                                                                                                          |
| `GUILD_DELETE` (not `unavailable`)          | `Discord\Jobs\MarkGuildLeft`           | `bot_present = false`                                                                                                                                                      |
| `GUILD_MEMBER_ADD` / `_UPDATE`              | `Access\Jobs\UpsertGuildMember`        | Account and membership updated (enabled servers only)                                                                                                                      |
| `GUILD_MEMBER_REMOVE`                       | `Access\Jobs\MarkMemberLeft`           | That membership `in_guild = false`. `guild.access` logs the person out if it was their last way in.                                                                        |

Quote capture (Phase 4) and stats (Phase 6) add more routes.

## Intents

`App\Discord\Gateway\GatewayIntents`: GUILDS, GUILD_MEMBERS\*, GUILD_MESSAGES, MESSAGE_CONTENT\*, GUILD_MESSAGE_REACTIONS, GUILD_VOICE_STATES, GUILD_PRESENCES\*.
\* Privileged: turn these on in the developer portal ([discord-app.md](discord-app.md)), or Discord closes the connection with code 4014.

## Liveness and restarts

- **Token check first:** `bot:run` calls `GET /users/@me` with the bot token before connecting. A rejected token (401) exits straight away with `Discord rejected DISCORD_BOT_TOKEN`, instead of DiscordPHP's unhandled-promise noise and a 3-minute wait.
- **No voice client:** DiscordPHP's voice manager is disabled (`disableVoiceClient`). The bot only reads voice _states_ (for stats) and never joins voice, which would need libdave.

- Each heartbeat ACK from Discord (about every 41 s) or incoming event refreshes the cache key `discord:bot:heartbeat`, at most every 30 s. `php artisan bot:status` reports online if a beat came within 90 s.
- **Watchdog:** if Discord has sent nothing for 180 s, the process exits with code 1 and Docker (`restart: unless-stopped`) starts it again. This covers fatal closes after which DiscordPHP stops reconnecting, e.g. `4014 Disallowed intents`. If the bot never finished connecting, the log tells you to check the privileged intents.
- SIGTERM/SIGINT close the connection cleanly.

## Running

Dev (Sail): the `bot` service is behind the compose profile `bot`, so `sail up` doesn't start it without a token. Once `DISCORD_BOT_TOKEN` is set:

```bash
# either add COMPOSE_PROFILES=bot to .env, or:
COMPOSE_PROFILES=bot vendor/bin/sail up -d
vendor/bin/sail logs -f bot
vendor/bin/sail artisan bot:status
```

After changing bot code, restart it: `vendor/bin/sail restart bot`. Long-running PHP doesn't reload code. Changes to jobs need `vendor/bin/sail restart horizon`.

Known noise: on start DiscordPHP logs `failed to initialize voice class … libdave is required`. It's harmless: the bot only _reads_ voice states (for stats) and never joins voice.

Prod: the `bot` service in `compose.prod.yaml` always runs (see [deployment.md](deployment.md)).

## Talking to Discord from jobs

Use `App\Discord\DiscordClient` (bound to `HttpDiscordClient`, bot token). In tests, bind `App\Discord\Testing\FakeDiscordClient`:

```php
app()->instance(DiscordClient::class, $discord = new FakeDiscordClient);
// ...
$discord->assertSent('777', 'saved');
```
