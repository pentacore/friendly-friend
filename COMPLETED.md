# Completed

Everything that is done, moved here from `ROADMAP.md` (which keeps only what is still open). Newest work is at the end of each section. Design details live in `docs/superpowers/specs/2026-11-26-friendly-friend-design.md`.

## Phases

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

## Phase 5: Quotes on the website

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

## Phase 6: Stats

- [x] Message, voice, reaction/emoji and game/presence tracking per server; hourly/daily rollups
- [x] Dashboards (heatmap, leaderboards, "who hangs out with whom") on `/stats`, plus stats on person pages
- [x] Opt-out on the website and with `/privacy optout|optin|status`; purge on opt-out; cached opted-out set checked in the bot router and again in the jobs

## Phase 7: Event planning (spec §14)

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

## Improvements after the phases

- [x] Discord's native event "Interested" becomes a going RSVP; the event description shows the real counts (bots can't set Interested)
- [x] Events: "Add to calendar" (.ics download)
- [x] Events: edit an expense
- [x] Events: vote on dates from Discord (a "Vote on dates" button opening a private picker)
- [x] Events: DM voters when a date is chosen
- [x] Events: edit a ride (not below the seats already taken)
- [x] Sidebar grouped into Quotes, Together and Admin
- [x] Stats: busiest channels on `/stats`
- [x] Stats: a "this week" card on the dashboard
- [x] Dark-mode browser tests: Pest + Playwright, `composer test:browser`, in CI.
- [x] Replace the starter kit's Laravel logo with a Friendly Friend mark (the highlighted quote glyph).
- [x] Restore removed quotes (admins): Admin → Removed quotes, and a Restore button on the quote page.
- [x] Favicon, touch icons, web manifest, a Discord avatar and a bot banner, generated from the logo mark (`scripts/render-icons.mjs`).
- [x] `GuildAccess` loaded the user's memberships several times per request: now once per `DiscordUser` instance.
- [x] `docs-updated` CI check also runs on pushes (compares with the commit before the push).
- [x] **Stats after bot downtime:** sessions that ended while the bot was down now end at its last heartbeat before the restart (only in the first 10 minutes after a start; events missed during the outage are still lost).
- [x] **SSR hydration mismatches**: dates and numbers now use one locale, timezone and "now" from the server; SSR also crashed (Echo on the server, `window` in setup) on every page with live updates and on event pages, silently falling back to client rendering. Fixed, with a hydration browser test in CI and SSR switched off in production (no SSR server there).
- [x] Telescope's tables are created only in local development (`shouldRun()`), not in production or tests.
- [x] Expired drafts: the preview turns into "expired" (no buttons) when the draft is pruned; drafts now live 14 minutes, inside Discord's 15-minute token for editing the reply.
- [x] The Discord edit form keeps line breaks inside a conversation line (as indented continuation lines, which the parser reads back).
- [x] Watched channels: a per-server option to publish picked-up quotes straight away, skipping review.
- [x] `/quote random` can answer privately, per server (Servers page).
- [x] Stats records on /stats: longest voice session, most messages in a day, longest streak of active days.
- [x] Events: organisers reorder questions (↑/↓).
- [x] Events: a currency per event (SEK, NOK, DKK, EUR, GBP, USD), fixed once there are costs.
- [x] Events: an expense can say which shopping list items it paid for (spec §14 `expense_id`).
- [x] Jev goes through OpenRouter (`OPENROUTER_API_KEY`, no waitlisted TypeSafe key), in one place: `App\Ai\Jev`.
- [x] Jev classifies what an @mention wants: save a quote, a random quote (posted in the channel) or help (DMed); capture stays the default.
- [x] Jev picks between several fuzzy person matches in `ResolvePerson` (or says it's someone new).
- [x] Typefinder 8.6: the upstream fixes for #245–#249 are in. The local Vue type-cache plugin and the `buildCommand: false` switch are gone, and broadcast payloads come from `broadcastWith()`'s PHPDoc shape instead of `#[TypefinderBroadcast]`.
- [x] Conventions adopted from media manager: `ai-rules.md` (narrowest glob, owner's rules, skills for procedures) with a test that every rule glob matches a file, the `formatting-project` and `running-browser-tests` skills, owner approval for new production packages (`dependencies.md`), a rule that a behaviour change updates every description of it, and Dependabot for Composer and npm.
- [x] Admin people search: `%` and `_` are literal, the nickname match is grouped, and the closest name comes first.
- [x] Mentions asking for "a quote from Bo" (a name, not an @mention) pick from that person, resolved like a speaker.
- [x] Quote search: one shared scope (`Quote::search()`).
- [x] Quote search matches prefixes while typing; `-word` excludes.
- [x] Quote search finds quotes by a person's current name and nicknames.
- [x] Quote feed: best-match order while searching, with a newest / top score / best match switch.
- [x] Quote cards mark the words a search matched.
- [x] Quote feed: a person picker next to the server select.
- [x] Discord: `/quote search text [person]`, private results with links (person as a Discord user option, not autocomplete).
- [x] Events list: search by name or place, and every past event through infinite scroll.
- [x] Events: a personal calendar feed link (Settings → Calendar) to subscribe to.
- [x] Privacy: "Download my data" as JSON on /settings/privacy.
- [x] More from media manager: `GLOSSARY.md` (domain words with their code names, and words to avoid), architecture tests for the "never do X" rules (strict types, final classes, `Model::query()`, `dispatch()`, no Mockery, no `env()` outside config, no services/listeners layer), rules for foreign key delete rules, `down()`, `$with`, seeders, command names and new job fields, a worktree rule, and `ROADMAP.md` grouped by what each item needs next.
- [x] Owner settings (`/admin/settings`): reminder times, the Maybe nudge, draft expiry and top-list sizes are `AppSetting`s with defaults and bounds instead of constants and config.
- [x] Browser layout gate: every page on a phone and a desktop, light and dark (no JavaScript errors, no sideways overflow, dark theme applied, every control named), with a test that every page route is in it, and the browser suite in 3 duration-balanced CI shards.
- [x] Release pipeline: after CI passes on `main`, semantic-release versions from conventional commits, writes `CHANGELOG.md`, tags and publishes a GitHub release, and the tagged image goes to GHCR, where `compose.prod.yaml` pulls it. The image is generic: the browser's Reverb endpoint moved from build-time `VITE_REVERB_*` to a runtime `<meta name="reverb-config">` (`REVERB_BROWSER_*`).
- [x] Quotes: search ignores accents (`f_unaccent`, `hakan` finds _Håkan_) and falls back to close spellings for typos when nothing matches exactly (`Quote::applySearch`, trigram `word_similarity`), on the feed and in `/quote search`
- [x] Discord: Jev routes @mentions that ask to find a quote ("find the one where Bo talks about IKEA", "who said …?") to the quote search; results are DMed like `/quote search`
- [x] Global search: the header search box (Ctrl+K, Cmd+K or /) finds quotes, people and events as you type, with keyboard picking; the unused starter-kit `AppHeader` and `AppHeaderLayout` are removed

## Bugs found and fixed along the way

- [x] Searching the quote feed (and admin people) never updated the page's `filters`: `reset: [...]` made the visit a partial reload of only the list, so the empty state said "No quotes yet" instead of "Nothing matches".

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
- [x] **Channel imports could run twice at once:** `ImportChannelQuotes` may run 600 s, but redis handed a reserved job to a second worker after `retry_after` (90 s). `retry_after` is 630 s now, and a test keeps every job's timeout below it.
- [x] **`withoutOverlapping()` on scheduled jobs protected nothing:** `Schedule::job()` releases the lock once the job is queued, so two `SendEventReminders` runs could overlap and DM a reminder twice. It and `PruneExpiredDrafts` are `ShouldBeUnique` now, and a test bans the lock on scheduled jobs.
- [x] `DatabaseSeeder` still set the starter kit's `name` and `email`, which the `users` table doesn't have, so `db:seed` failed. It seeds nothing now and says why.
- [x] Five PHP files (two migrations, `bootstrap/providers.php`, `HandleAppearance`, a unit test) lacked `declare(strict_types=1)`; the new architecture test caught them.
