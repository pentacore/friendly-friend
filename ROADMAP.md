# Roadmap

What is still open: ideas, known gaps, setup still to do, and reports for other projects. **Keep this file current:** add bugs, follow-ups and feature ideas as soon as they come up, in the same commit as the work that found them. When something is done, move it to [`COMPLETED.md`](COMPLETED.md) in that same commit. Design details live in `docs/superpowers/specs/2026-11-26-friendly-friend-design.md`.

All seven build phases are done (see `COMPLETED.md`).

## Ideas (not scheduled)

- [ ] Jev (TypeSafe) `Choice` to classify what an @mention wants (save quote, random quote, help) instead of always treating it as a capture
- [ ] Jev to pick between ambiguous fuzzy person matches in `ResolvePerson`
- [ ] Let server admins (not only owners) manage their own server's settings
- [ ] Events: render the description as Markdown (plain text with line breaks for now; needs a Markdown library, which is a new dependency)
- [ ] Events: link a shopping item to the expense that paid for it (spec §14 `expense_id`)
- [ ] Events: give the bot **Create Events / Manage Events** on your servers (mirroring is skipped and logged without it), and pick an events channel on the Servers page
- [ ] Events: reorder questions, make a question required, ask questions from the Discord RSVP reply
- [ ] Events: per-event currency picker (always SEK for now)
- [ ] Stats: weekly recap post in Discord (busiest hour, top talker, longest voice hangout)
- [ ] Stats: streaks and records (longest voice session, most messages in a day)
- [ ] Quotes: "Who said it?" game, in Discord and on the web (show a quote, everyone guesses the person, running scores)
- [ ] Yearly recap ("Wrapped") per server and per person: top quotes, most quoted, voice nights, events attended, money spent
- [ ] Birthdays: date on the profile, a bot greeting in Discord (maybe with a random quote from that person)
- [ ] Events: Swish links / QR codes on settle-up transfers (amount and message filled in)
- [ ] Events: recurring events (e.g. every other Friday) and templates / copy an earlier event with its food, questions and costs setup
- [ ] Quotes: quote of the day / "on this day" auto-post in a chosen channel
- [ ] Quotes: images and screenshots as quotes (attachments)
- [ ] Quotes: let the quoted person ask to hide a quote (counterpart to the stats opt-out)
- [ ] Quotes: tags or more emoji reactions than 👍/👎, and browsing by them
- [ ] Quotes: quote book export (PDF / JSON)
- [ ] Events: general polls ("where do we eat?") reusing the date-vote machinery
- [ ] Events: capacity limit with a waitlist
- [ ] Events: per-user calendar subscription feed (iCal URL) instead of one-off .ics downloads
- [ ] Events: memories page afterwards (photos plus quotes captured during the event)
- [ ] Notification settings: choose which DMs you get (reminders, date chosen, "you were quoted")
- [ ] Privacy: "download my data" export on `/settings/privacy`
- [ ] Admin: audit log (who removed, merged, restored what)
- [ ] Web push notifications for the installed PWA (the web manifest already exists)

## Follow-ups and known gaps

- [ ] **Playwright system libraries in Sail:** installed into the running container only (`playwright install-deps`). Bake them into the Sail runtime (`sail:publish`, Dockerfile) so a rebuild keeps them.
- [ ] **AI keys not set** in the dev `.env`. Quotes are parsed without AI until `ANTHROPIC_API_KEY` / `OPENAI_API_KEY` / `GEMINI_API_KEY` is set. `TYPESAFE_API_KEY` (Jev) is optional and waitlisted.
- [ ] **Bot can't see most channels** on "Claesson Notify" (Discord masks them as `___hidden___`). Give the bot View Channel / Read Message History where quotes, imports and stats should work.
- [ ] **Stats need the Presence intent** turned on in the developer portal (game sessions), and the bot must see a channel to count messages there (see "Bot can't see most channels").
- [ ] Old `stat_*` rows are kept forever. Add a retention setting (e.g. delete hourly message buckets older than two years) if the tables grow.
- [ ] Boost MCP server: configured through Sail in `.mcp.json`, but this Claude Code session started before it existed. Restart Claude Code in the project to use its tools.

## Upstream reports (other projects)

- [ ] **pest-plugin-browser:** each browser test run leaves its `playwright run-server` processes behind (about 60, 120 MB each, had piled up in the Sail container). Also, its console capture only wraps `console.log`, so `assertNoConsoleLogs()` misses `console.error` (e.g. Vue hydration errors).
- [ ] **Rector:** removing a redundant `(int)` cast changed `(int) ((int) $id >> 22) % 6` into `(int) $id >> 22 % 6` (different precedence, wrong result). Worked around in `DiscordUser::avatarUrl()` and pinned by a test. Report to rectorphp/rector.
- [ ] **laravel-typefinder:** the README documents `vendor:publish --tag=typefinder-skill`, but 8.5.0 ships the skill as a Boost skill (`resources/boost/skills`) and has no such tag. Reported: [#245](https://github.com/pentacore/Laravel-Typefinder/issues/245).
- [ ] **laravel-typefinder / Vite plugin:** generating needs a database (schema), which Docker image builds don't have. A built-in way to skip build-time generation when types are committed would help (we use `TYPEFINDER_SKIP_BUILD` with `buildCommand: false`). Reported: [#246](https://github.com/pentacore/Laravel-Typefinder/issues/246).
- [ ] **laravel-typefinder:** page prop types are emitted verbatim, so a PHPDoc shape like `array{id: int}` produced invalid TypeScript without a warning, even with `--strict`. Validate or convert prop types. Reported: [#247](https://github.com/pentacore/Laravel-Typefinder/issues/247).
- [ ] **@pentacore/vite-plugin-laravel-typefinder:** after regenerating `.d.ts` files during `vite dev`, Vue's SFC type cache keeps the old types (`defineProps<PageProps['x']>()` then compiles to no props). The plugin could call `invalidateTypeCache()` from `vue/compiler-sfc` for written files, as our local plugin does. Reported: [#248](https://github.com/pentacore/Laravel-Typefinder/issues/248).
- [ ] **laravel-typefinder:** `broadcastWith()` with a PHPDoc `@return array{…}` shape still needs `#[TypefinderBroadcast(payload: …)]`; reading the PHPDoc shape (as for resources) would avoid the duplication. Reported: [#249](https://github.com/pentacore/Laravel-Typefinder/issues/249).
- [ ] **pest-plugin-drift:** `pest --drift` rejected its directory argument in this setup ("only accepts the directory to convert as argument"); the tests were converted by hand.
- [ ] **DiscordPHP:** an invalid token produces an unhandled promise rejection (`InvalidTokenException`) on the gateway lookup, and the client stays alive after critical close codes.
