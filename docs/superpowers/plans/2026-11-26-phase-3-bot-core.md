# Phase 3: Bot Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** A long-running `php artisan bot:run` process keeps a Discord gateway connection, turns gateway events into queued jobs (starting with member join, update and leave), reports a heartbeat, and runs as its own service in dev and prod. All Discord REST calls go through a `DiscordClient` interface with an in-memory fake.

**Architecture:** DiscordPHP is only the transport. The command subscribes to DiscordPHP's `raw` event and hands every dispatch (`op 0`) to `App\Discord\Gateway\GatewayEventRouter::route(string $type, array $payload)`. That's plain PHP, unit-tested with JSON fixtures. The router drops events from other guilds and dispatches jobs. It doesn't touch the database (spec §3: the bot process holds no business logic). Horizon workers run the jobs. Liveness is a Redis cache key written every 30 s (`BotHeartbeat`).

**Tech Stack:** team-reflex/discord-php 10.73 (ReactPHP), Laravel queues (Horizon), Pest.

## Global Constraints

- Spec §3, §8 (intents), §9 (bot error handling).
- Gateway intents: GUILDS, GUILD_MEMBERS, GUILD_MESSAGES, MESSAGE_CONTENT, GUILD_MESSAGE_REACTIONS, GUILD_VOICE_STATES, GUILD_PRESENCES.
- DiscordPHP options: `loadAllMembers=false`, `storeMessages=false`, `retrieveBans=false`. The DB-backed member sync is the source of truth, not DiscordPHP's cache.
- Handlers must never throw out of the event loop: catch, log, continue.
- Snowflakes are strings.
- Every commit updates docs and guidelines, plus `boost:update` and `typefinder:generate`.

## File map

| File                                         | Responsibility                                                                                                                                       |
| -------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Discord/DiscordClient.php`              | Interface: `configured(): bool`, `guildMembers(string $guildId): iterable<GuildMemberData>`, `sendMessage(string $channelId, string $content): void` |
| `app/Discord/HttpDiscordClient.php`          | Bot-token REST implementation (replaces `DiscordBotApi`)                                                                                             |
| `app/Discord/Testing/FakeDiscordClient.php`  | In-memory fake: `withMembers()`, `sentMessages()`, `assertSent()`                                                                                    |
| `app/Discord/Gateway/GatewayEventRouter.php` | `route(string $type, array $payload): void` → dispatches jobs                                                                                        |
| `app/Discord/Gateway/BotHeartbeat.php`       | `beat()`, `lastBeatAt(): ?CarbonImmutable`, `isOnline(): bool`                                                                                       |
| `app/Discord/Gateway/GatewayIntents.php`     | The intents bitmask (one place)                                                                                                                      |
| `app/Console/Commands/RunBot.php`            | `bot:run` (DiscordPHP wiring, signals, heartbeat timer)                                                                                              |
| `app/Console/Commands/BotStatus.php`         | `bot:status`                                                                                                                                         |
| `app/Access/Jobs/UpsertGuildMember.php`      | Job: upsert from `GuildMemberData`                                                                                                                   |
| `app/Access/Jobs/MarkMemberLeft.php`         | Job: `in_guild=false`                                                                                                                                |
| `tests/Fixtures/gateway/*.json`              | Recorded-shape payloads                                                                                                                              |
| `compose.yaml`                               | `bot` service under the compose profile `bot` (dev)                                                                                                  |
| `compose.prod.yaml`                          | `bot` service                                                                                                                                        |

## Tasks

### Task 1: DiscordClient interface and fake

- [ ] Tests: `HttpDiscordClient::sendMessage` posts `{content}` to `channels/{id}/messages` with the bot token. The existing pagination test moves to `HttpDiscordClient`. `FakeDiscordClient` returns the configured members and records sent messages. The container resolves `DiscordClient` to `HttpDiscordClient`.
- [ ] Rename `DiscordBotApi` → `HttpDiscordClient implements DiscordClient`, bind it in `AppServiceProvider`, and make `SyncGuildMembers` depend on the interface. Commit.

### Task 2: Gateway router and member jobs

- [ ] Tests with fixtures: `GUILD_MEMBER_ADD` and `GUILD_MEMBER_UPDATE` for our guild → `UpsertGuildMember` pushed with matching data. `GUILD_MEMBER_REMOVE` → `MarkMemberLeft`. `GUILD_CREATE` for our guild → `SyncGuildMembers`. Events for another guild → nothing. Unknown types → nothing. A malformed payload → nothing (logged), no exception. The jobs themselves: upsert creates or updates, and `MarkMemberLeft` flips `in_guild` (then `guild.access` revokes).
- [ ] Implement. Commit.

### Task 3: bot:run, heartbeat, bot:status

- [ ] Tests: `BotHeartbeat` beat, then online. A stale beat (90 s or more) is offline. `bot:run` without a token or guild exits with a failure and a clear message. `bot:status` reports online or offline. `GatewayIntents::value()` equals the OR of the 7 intents.
- [ ] Implement the command: build `Discord` with the options above and the Laravel Monolog logger. Use `on('init')`: log ready, start a 30 s heartbeat timer, beat right away. Use `on('raw')`: for `op === 0`, normalise to an array (`json_decode(json_encode($data->d), true)`) and route it inside try/catch. Handle SIGTERM/SIGINT with `$discord->close()`. Commit.

### Task 4: Run it as a service

- [ ] `compose.yaml`: a `bot` service (`php artisan bot:run`, `restart: unless-stopped`, `profiles: [bot]`), so `sail up` doesn't crash-loop it without a token. Start it with `COMPOSE_PROFILES=bot` in `.env`. `compose.prod.yaml`: a `bot` service with the healthcheck disabled.
- [ ] Docs: `docs/bot.md` (what it does, running, logs, status), plus updates to `docs/architecture.md`, `docs/deployment.md`, `docs/setup.md` and `.ai/guidelines/architecture.md` (router pattern, no DB in the bot). Run every check, commit, push, check CI.
