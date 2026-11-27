# Roadmap

Everything that is planned, known to be missing, or was found along the way. **Keep this file current:** add bugs, follow-ups and feature ideas as soon as they come up, in the same commit as the work that found them, and tick them off when done. Design details live in `docs/superpowers/specs/2026-11-26-friendly-friend-design.md`.

## Status

| Phase | What                                                                                                                              | State   |
| ----- | --------------------------------------------------------------------------------------------------------------------------------- | ------- |
| 1     | Foundation: Laravel 13, Sail PHP 8.5, Postgres, tooling, Typefinder, Boost, CI, prod image                                        | ✅ done |
| 2     | Discord-only login, access by server roles                                                                                        | ✅ done |
| 3     | Bot core: gateway process, event router, member sync, watchdog                                                                    | ✅ done |
| 3b    | Multiple servers, owners in `.env`, `/admin/guilds`                                                                               | ✅ done |
| 4     | Quotes in Discord: capture (4 ways), AI cleanup, preview/edit/save, `/quote random`, channel import                               | ✅ done |
| 5     | Quotes on the website: feed, search, pages, editing, live voting, this-or-that, top lists, people tools, import review, dashboard | ✅ done |
| 6     | Server stats with opt-out: messages, reactions, voice, games, rollups, `/stats`, person stats, `/settings/privacy`, `/privacy`    | ✅ done |
| 7     | Event planning: RSVP, food, transport, costs with settle-up, Discord announcements, native events and reminders                   | ✅ done |

## Planned features

### Phase 5: Quotes on the website

- [x] Quote feed (newest first, filter by person and server), quote page with permalink and edit history
- [x] Full-text search (Postgres `tsvector` over lines and context; deferred from Phase 4)
- [x] Editing on the web (submitter and admins, `QuotePolicy`), every edit writes a revision
- [x] Voting (👍/👎) with live counts over Reverb
- [x] This-or-that with Elo ranking
- [x] Top lists (all-time, this month, by Elo, most quoted people, top submitters)
- [x] People pages; admin: merge people, manage aliases, link a person to a Discord user
- [x] **Import review queue** for `pending_review` quotes from channel imports (approve, edit, reject, approve all), with import progress (polling while running)
- [x] Remove quotes (admins); restore is not built yet
- [x] Show quotes in the `"quote text" - Person` style on the web too (as in Discord)

### Phase 6: Stats

- [x] Message, voice, reaction/emoji and game/presence tracking per server; hourly/daily rollups
- [x] Dashboards (heatmap, leaderboards, "who hangs out with whom") on `/stats`, plus stats on person pages
- [x] Opt-out on the website and with `/privacy optout|optin|status`; purge on opt-out; cached opted-out set checked in the bot router and again in the jobs

### Phase 7: Event planning (spec §14)

- [x] Events per server: RSVP (going/maybe/can't, guests, note, needs a ride), attendee list
- [x] Food: bring list with claims, shared shopping list, meal plan, dietary needs on the person's profile
- [x] Transport: rides with seats, pickup points, "needs a ride" list
- [x] Costs: expenses (equal or exact splits, minor units), balances, minimal settle-up transfers, payments
- [x] Watched channels: quotes posted without the bot are picked up for review (📝 reaction)
- [x] Plan questions, food, a ride and costs while creating an event; "everyone going" cost split; no past dates; wider event pages
- [x] Date vote: propose several dates, everyone answers yes / if need be / no, organisers choose one (votes become RSVPs)
- [x] Event questions (yes/no, pick one, free text) answered with the RSVP; counts for everyone, names for organisers
- [x] "Answer by" deadline: closes Going/Maybe (Can't stays open), nudges Maybes a day before, greys out the Discord buttons
- [x] Discord: announcement post with RSVP buttons, native scheduled-event mirror (needs the bot's **Create/Manage Events** permission), reminder DMs 24 h and 2 h before
- [x] Live updates per event over Reverb

### Ideas (not scheduled)

- [ ] Jev (TypeSafe) `Choice` to classify what an @mention wants (save quote, random quote, help) instead of always treating it as a capture
- [ ] Jev to pick between ambiguous fuzzy person matches in `ResolvePerson`
- [ ] Sync Discord's native event "Interested" back to RSVPs (needs the `GUILD_SCHEDULED_EVENTS` intent)
- [ ] Let server admins (not only owners) manage their own server's settings
- [ ] Per-server opt-in for posting `/quote random` publicly vs privately
- [ ] Events: render the description as Markdown (plain text with line breaks for now; needs a Markdown library, which is a new dependency)
- [ ] Events: add an "Add to calendar" (.ics) link
- [ ] Events: edit an expense (now: remove and add again); link a shopping item to the expense that paid for it (spec §14 `expense_id`)
- [ ] Events: give the bot **Create Events / Manage Events** on your servers (mirroring is skipped and logged without it), and pick an events channel on the Servers page
- [x] Events: vote on dates from Discord (a "Vote on dates" button opening a private picker)
- [ ] Events: DM voters when a date is chosen
- [ ] Watched channels: optionally publish picked-up quotes straight away (skip review) per server
- [ ] Events: reorder questions, make a question required, ask questions from the Discord RSVP reply
- [ ] Events: per-event currency picker (always SEK for now)
- [ ] Events: edit a ride (seats, time) instead of cancelling and offering it again
- [x] Sidebar grouped into Quotes, Together and Admin
- [ ] Stats: weekly recap post in Discord (busiest hour, top talker, longest voice hangout)
- [ ] Stats: per-channel breakdown on `/stats` (channel names are already cached on `guilds.channels`)
- [ ] Stats: a small "this week" card on the dashboard
- [ ] Stats: streaks and records (longest voice session, most messages in a day)

## Follow-ups and known gaps

- [x] Dark-mode browser tests: Pest + Playwright, `composer test:browser`, in CI.
- [ ] **Playwright system libraries in Sail:** installed into the running container only (`playwright install-deps`). Bake them into the Sail runtime (`sail:publish`, Dockerfile) so a rebuild keeps them.
- [x] Replace the starter kit's Laravel logo with a Friendly Friend mark (the highlighted quote glyph).
- [x] Restore removed quotes (admins): Admin → Removed quotes, and a Restore button on the quote page.
- [x] Favicon, touch icons, web manifest, a Discord avatar and a bot banner, generated from the logo mark (`scripts/render-icons.mjs`).
- [ ] **AI keys not set** in the dev `.env`. Quotes are parsed without AI until `ANTHROPIC_API_KEY` / `OPENAI_API_KEY` / `GEMINI_API_KEY` is set. `TYPESAFE_API_KEY` (Jev) is optional and waitlisted.
- [ ] **Bot can't see most channels** on "Claesson Notify" (Discord masks them as `___hidden___`). Give the bot View Channel / Read Message History where quotes, imports and stats should work.
- [ ] `GuildAccess` loads the user's memberships several times per request (middleware, `isAdmin`, `isOwner` in shared props). Memoize per request.
- [ ] The edit form flattens line breaks inside a conversation line to `/` (Discord text inputs have no structure). Single-speaker quotes keep their line breaks.
- [ ] Expired drafts: the preview message stays with its buttons, which then answer "expired". Could edit the preview to say "expired" when pruning (needs the interaction token or DM message ID).
- [ ] Draft lifetime (15 min) equals the interaction token lifetime. A preview edited at minute 14 still works, but there's no margin.
- [ ] Telescope's migration also runs in production (creates unused tables). Harmless; could be excluded.
- [ ] `docs-updated` CI check only warns on pull requests; direct pushes to `main` aren't checked.
- [ ] **Stats after bot downtime:** events missed while the bot is down are lost. On reconnect, sessions that still match are assumed to have continued, and the rest are closed at reconnect time rather than at the last heartbeat, which can overcount voice and game time after a long outage. Could close stale sessions at the previous heartbeat instead.
- [ ] **Stats need the Presence intent** turned on in the developer portal (game sessions), and the bot must see a channel to count messages there (see "Bot can't see most channels").
- [ ] Old `stat_*` rows are kept forever. Add a retention setting (e.g. delete hourly message buckets older than two years) if the tables grow.
- [ ] Boost MCP server: configured through Sail in `.mcp.json`, but this Claude Code session started before it existed. Restart Claude Code in the project to use its tools.

## Bugs found and fixed along the way

Kept as a record of pitfalls. The lessons are also written into `.ai/rules/` (traps are in `general.md`).

- [x] Larastan max errors in starter-kit code (nullable `$request->user()`, `Route::inertia` returning mixed): fixed at the source, no baseline.
- [x] PHPStan cache was committed (`storage/framework/phpstan`): removed from history before the first push, now git-ignored.
- [x] Fuzzy matching threshold 0.6 missed "Fredrick" vs "Fredrik" (0.55): lowered to 0.5, and the preview shows every match.
- [x] Bot heartbeat always "offline": Redis returns numbers as strings. Stored as an ISO string now, with a regression test against real Redis.
- [x] Bot stayed alive after a fatal gateway close (4004/4014) without reconnecting: traffic-based liveness plus a watchdog that exits so Docker restarts it.
- [x] Invalid bot token caused a DiscordPHP unhandled-promise trace and a 3-minute wait: token preflight over REST.
- [x] DiscordPHP logged a libdave voice error on every start: `disableVoiceClient`.
- [x] `REVERB_HOST=localhost` in dev: server-side broadcasts never reached the Reverb container. Split into server (`REVERB_*`) and browser (`VITE_REVERB_*`) endpoints.
- [x] Prod image baked `localhost:8080` and the example Reverb key into the frontend: compose build args.
- [x] First deploy migration race: one-shot `migrate` service.
- [x] Prod logs invisible: `LOG_CHANNEL=stderr`.
- [x] No trusted proxies, so http URLs appeared behind TLS: `TRUSTED_PROXIES` (`app.trusted_proxies`).
- [x] Prod `web` never healthy: the base image's check polls a disabled admin API; `/up` healthcheck now.
- [x] Servers page counted members with one query per server: `withCount`.
- [x] PHP turns numeric snowflake array keys into ints, which broke linking mention names: build maps with string IDs (rule in guidelines).
- [x] The bot REST client retried every failure, including a 403 "DMs closed": retries only 429/5xx/connection errors.
- [x] Postgres `jsonb` reorders object keys, so a test compared JSON by key order: compare by value.
- [x] **Vue silently compiled pages with no props:** `defineProps<PageProps['x']>()` reads Typefinder's .d.ts through Vue's type cache, which the dev server never invalidates. Login error messages and new pages lost their props until a restart. Fixed with a Vite plugin that invalidates the cache on Typefinder output, plus browser tests to catch it.
- [x] Dark-mode error text unreadable (the starter kit's dark `--destructive` token was too dark for text): brightened the token.
- [x] Highlighter swipe hidden behind the card background (negative z-index without its own stacking context): `isolate`.
- [x] Mid-refactor login hit a deleted class; reloading the callback re-sent a used OAuth code (`invalid_grant`). Not a code bug; documented in `docs/auth.md`.

- [x] Parallel tests failed with Postgres `out of shared memory` after the events schema grew: Sail's Postgres now runs with `max_locks_per_transaction=256`.

- [x] Tests could reach real services: page tests called the Vite SSR dev server whenever it ran, and the real `DISCORD_BOT_TOKEN` from `.env` let jobs call Discord (it hit random fake server IDs, so nothing was created). Feature tests now block stray HTTP, SSR is off in tests, and the bot token is blanked in `phpunit.xml`.

## Upstream reports (other projects)

- [ ] **Rector:** removing a redundant `(int)` cast changed `(int) ((int) $id >> 22) % 6` into `(int) $id >> 22 % 6` (different precedence, wrong result). Worked around in `DiscordUser::avatarUrl()` and pinned by a test. Report to rectorphp/rector.
- [ ] **laravel-typefinder:** the README documents `vendor:publish --tag=typefinder-skill`, but 8.5.0 ships the skill as a Boost skill (`resources/boost/skills`) and has no such tag.
- [ ] **laravel-typefinder / Vite plugin:** generating needs a database (schema), which Docker image builds don't have. A built-in way to skip build-time generation when types are committed would help (we use `TYPEFINDER_SKIP_BUILD` with `buildCommand: false`).
- [ ] **laravel-typefinder:** page prop types are emitted verbatim, so a PHPDoc shape like `array{id: int}` produced invalid TypeScript without a warning, even with `--strict`. Validate or convert prop types.
- [ ] **@pentacore/vite-plugin-laravel-typefinder:** after regenerating `.d.ts` files during `vite dev`, Vue's SFC type cache keeps the old types (`defineProps<PageProps['x']>()` then compiles to no props). The plugin could call `invalidateTypeCache()` from `vue/compiler-sfc` for written files, as our local plugin does.
- [ ] **laravel-typefinder:** `broadcastWith()` with a PHPDoc `@return array{…}` shape still needs `#[TypefinderBroadcast(payload: …)]`; reading the PHPDoc shape (as for resources) would avoid the duplication.
- [ ] **pest-plugin-drift:** `pest --drift` rejected its directory argument in this setup ("only accepts the directory to convert as argument"); the tests were converted by hand.
- [ ] **DiscordPHP:** an invalid token produces an unhandled promise rejection (`InvalidTokenException`) on the gateway lookup, and the client stays alive after critical close codes.
