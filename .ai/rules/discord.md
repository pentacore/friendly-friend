---
paths:
    - 'app/Discord/**'
    - 'app/Console/Commands/RunBot.php'
    - 'tests/Fixtures/gateway/**'
---

# Discord

## Bot process

- `RunBot` (`bot:run`) forwards raw gateway dispatches to `GatewayEventRouter::route($type, $payload)`. The router validates the payload, builds a DTO and **queues a job**. Jobs decide whether the server is enabled.
- **No DB queries, HTTP calls or business logic in the router or the command.** The only exceptions: reading the cache (edit form, the `OptedOutMembers` set), and the **interaction acknowledgement**, which has to be sent within Discord's 3 seconds. `bot:run` refreshes the opted-out set once at startup, before connecting.
- Stats events from bots (`member.user.bot`) and opted-out members are dropped in the router (see `stats.md`). Timestamps the payload lacks (reactions, voice, presence) are taken in the router (`now()`), not in the job, so queue delays don't shift them.
- The acknowledgement body is decided by the pure `InteractionAck::body($payload)` and sent through `InteractionAcknowledger`. In the bot that's `Gateway\DiscordPhpAcknowledger` (async, never blocks). Handling happens in a queued job that edits the response.
- A new gateway event means a router branch, a JSON fixture in `tests/Fixtures/gateway/` or a builder in `Tests\Support\DiscordPayloads`, and a test.
- Liveness comes from real Discord traffic, not timers. The watchdog exits non-zero when Discord goes silent, so Docker restarts the bot. The token is checked over REST before connecting. The voice client is disabled.

## Talking to Discord

- Bot-token REST calls go through the `DiscordClient` interface (`HttpDiscordClient`; tests bind `Testing\FakeDiscordClient`). Add methods there rather than calling Discord's HTTP API elsewhere. `DiscordUserApi` is only for the OAuth user token at login.
- Retries cover only transient failures (connection errors, 429, 5xx), never 4xx.
- Read nested Discord JSON with `Data\Payload::get()/array()/string()`, not raw `$a['x']['y']` on mixed. DTOs are built with `fromPayload()`.
- Messages are full payload arrays (`content`, `components`, …). `HttpDiscordClient` adds `allowed_mentions: none` unless the message sets its own.
- Custom IDs follow the `quote:<action>:<id>` pattern (`Interactions\CustomId`). Commands are defined in `Interactions\QuoteCommands` and registered per enabled server by `Jobs\RegisterGuildCommands`.
- Channels Discord masks as `___hidden___` (the bot can't see them) are left out of the channel cache.
