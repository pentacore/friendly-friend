# Discord bot

`php artisan bot:run` holds the Discord gateway (websocket) connection. It's deliberately thin:

```
Discord gateway ──raw dispatch──► RunBot ──► GatewayEventRouter::route($type, $payload)
                                                │  checks the guild, builds a DTO
                                                ▼
                                           queued job ──► Horizon worker (DB, REST, AI)
```

- **No business logic or DB access in the bot process.** `App\Discord\Gateway\GatewayEventRouter` is plain PHP: it filters to `DISCORD_GUILD_ID`, maps the payload to a DTO and dispatches a job. It's unit-tested with JSON fixtures in `tests/Fixtures/gateway/`.
- DiscordPHP is used only as the transport (`loadAllMembers`, `storeMessages` and `retrieveBans` are off). The database is the source of truth.
- One bad event never stops the loop: routing errors are caught and logged.

## Events handled

| Gateway event                             | Job                             | Effect                                               |
| ----------------------------------------- | ------------------------------- | ---------------------------------------------------- |
| `GUILD_CREATE` (our guild)                | `Access\Jobs\SyncGuildMembers`  | Full member refresh after connecting or reconnecting |
| `GUILD_MEMBER_ADD`, `GUILD_MEMBER_UPDATE` | `Access\Jobs\UpsertGuildMember` | Member row created or updated (roles, nick, avatar)  |
| `GUILD_MEMBER_REMOVE`                     | `Access\Jobs\MarkMemberLeft`    | `in_guild = false`, and `guild.access` logs them out |

Quote capture (Phase 4) and stats (Phase 6) add more routes.

## Intents

`App\Discord\Gateway\GatewayIntents`: GUILDS, GUILD_MEMBERS\*, GUILD_MESSAGES, MESSAGE_CONTENT\*, GUILD_MESSAGE_REACTIONS, GUILD_VOICE_STATES, GUILD_PRESENCES\*.
\* Privileged: turn these on in the developer portal ([discord-app.md](discord-app.md)), or Discord closes the connection with code 4014.

## Liveness and restarts

- Each heartbeat ACK from Discord (about every 41 s) or incoming event refreshes the cache key `discord:bot:heartbeat`, at most every 30 s. `php artisan bot:status` reports online if a beat came within 90 s.
- **Watchdog:** if Discord has sent nothing for 180 s, the process exits with code 1 and Docker (`restart: unless-stopped`) starts it again. This covers fatal closes such as `4004 Authentication failed`, after which DiscordPHP stops reconnecting. With a bad token the bot therefore restarts every few minutes, and the log says why.
- SIGTERM/SIGINT close the connection cleanly.

## Running

Dev (Sail): the `bot` service is behind the compose profile `bot`, so `sail up` doesn't start it without a token. Once `DISCORD_BOT_TOKEN` and `DISCORD_GUILD_ID` are set:

```bash
# either add COMPOSE_PROFILES=bot to .env, or:
COMPOSE_PROFILES=bot vendor/bin/sail up -d
vendor/bin/sail logs -f bot
vendor/bin/sail artisan bot:status
```

After changing bot code, restart it: `vendor/bin/sail restart bot`. Long-running PHP doesn't reload code. Changes to jobs need `vendor/bin/sail restart horizon`.

Prod: the `bot` service in `compose.prod.yaml` always runs (see [deployment.md](deployment.md)).

## Talking to Discord from jobs

Use `App\Discord\DiscordClient` (bound to `HttpDiscordClient`, bot token). In tests, bind `App\Discord\Testing\FakeDiscordClient`:

```php
app()->instance(DiscordClient::class, $discord = new FakeDiscordClient);
// ...
$discord->assertSent('777', 'saved');
```
