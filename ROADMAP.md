# Roadmap

What is still open: ideas, known gaps, setup still to do, and reports for other projects. **Keep this file current:** add bugs, follow-ups and feature ideas as soon as they come up, in the same commit as the work that found them. When something is done, move it to [`COMPLETED.md`](COMPLETED.md) in that same commit. Design details live in `docs/superpowers/specs/2026-11-26-friendly-friend-design.md`.

All seven build phases are done (see `COMPLETED.md`).

## Ideas (not scheduled)

- [ ] Let server admins (not only owners) manage their own server's settings
- [ ] Events: render the description as Markdown (plain text with line breaks for now; needs a Markdown library, which is a new dependency)
- [ ] Events: give the bot **Create Events / Manage Events** on your servers (mirroring is skipped and logged without it), and pick an events channel on the Servers page
- [ ] Events: make a question required, ask questions from the Discord RSVP reply
- [ ] Stats: weekly recap post in Discord (busiest hour, top talker, longest voice hangout)
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

### Search

Today there are two searches: the quote feed (`QuoteController::index`, `search_vector @@ websearch_to_tsquery('simple', …)`, newest first) and admin people (`Admin\PeopleController::index`, `ilike` on names and aliases). Ideas in rough priority order:

- [ ] Quotes: one shared search scope (e.g. `Quote::search($text)`) so the feed, person pages and a Discord command run the same query, and the items below land in one place
- [ ] Quotes: match while typing. `websearch_to_tsquery` only matches whole words, so "Uncl" finds nothing until "Uncle" is typed in full. Turn the last term (or every term) into a prefix query (`word:*`, built with `to_tsquery` from sanitised words)
- [ ] Quotes: find quotes by a person's current name and nicknames. `search_text` only holds `speaker_name` as written when the quote was saved, so renaming a person, adding an alias or `MergePeople` doesn't make their quotes findable under the new name. Either add the linked people's `display_name` and aliases to `search_text` (and rebuild it on rename, alias change and merge), or match the query against people too and OR in their quotes
- [ ] Quotes: tolerate typos and missing accents ("hakan" → "Håkan", "kanelbule" → "kanelbulle"). `pg_trgm` is already installed (for `ResolvePerson`); add a trigram GIN index on `search_text` and fall back to `word_similarity` when full-text finds nothing. Accent folding needs the `unaccent` extension (an immutable wrapper for the generated column)
- [ ] Quotes: sort by relevance (`ts_rank`) when there is a query, with a sort switch (newest / top score / best match) on the feed
- [ ] Quotes: highlight the matched words in the quote cards
- [ ] Quotes feed: a person picker next to the server select. The person filter is only reachable from a person page (`?person=slug`), and an unknown slug is silently ignored
- [ ] Discord: `/quote search <text>` with autocomplete on the person option, reusing the shared scope (only `add` and `random` exist)
- [ ] Global search / command palette (Ctrl+K) across quotes, people and events. The unused starter-kit `AppHeader.vue` has a search button that does nothing; wire it up or delete the component
- [ ] Events: search by title, and reach past events older than the latest 30 (`EventController::index` caps them with no paging)
- [ ] Quotes: search by meaning ("the one where Bo complained about IKEA", "cinnamon bun" finding "kanelbulle"). Embed each quote with laravel/ai embeddings in a queued job on save and edit, store the vector with `pgvector`, and run a hybrid query: full-text and trigram matches for exact words, vector similarity as a fallback or second ranking signal (always inside `visibleTo`). Needs `pgvector` in the Postgres image (Sail's default doesn't ship it) and an AI key (none set in dev yet). Do it after the Postgres items above. Cost limits: indexing is a one-off of roughly 30–150 tokens per quote (cents for the whole archive), then only new and edited quotes. Embed the query only when the word search finds few results or the search is submitted, never on every debounced keystroke, and cache query embeddings for a while. Ollama (local, supported by laravel/ai) is a zero-token option; Anthropic has no embedding models
- [ ] Discord: let Jev route @mentions that ask for a quote ("find the one where…") to search instead of saving a new quote. Jev is a classifier (`Classification`), so it picks the intent, not the results. Add a search intent to `MentionIntentClassifier`. Cost: one small classification per @mention, cheaper than the AI parsing a save already does
- [ ] _Low priority, possibly never:_ Quotes: turn plain-language queries into filters ("Bo's quotes from last summer" → person and date range) with an LLM using structured output. A general model fits this better than Jev. It is the only AI search item with a real per-search cost (a full model call, roughly 300–800 tokens in, and slow), so if built: call it only on submit or from the Discord command, and only when the query reads like a sentence rather than keywords; never send the list of people, let the model return the name as written and resolve it with `ResolvePerson`

Considered and skipped for now: **Typesense / Meilisearch** (through Laravel Scout). They give prefix matching, typo tolerance, ranking and highlighting out of the box, but Postgres covers all of that at this data size (prefix `tsquery`, `pg_trgm`, `ts_rank`, `ts_headline`, `unaccent`). They would add a service to run and back up, an index to keep in sync (edits, merges, removals, per-server visibility filters) and a new dependency, and they still can't search by meaning. Revisit if the quote count grows by orders of magnitude.

## Follow-ups and known gaps

- [ ] **Playwright system libraries in Sail:** installed into the running container only (`playwright install-deps`). Bake them into the Sail runtime (`sail:publish`, Dockerfile) so a rebuild keeps them.
- [ ] **AI keys not set** in the dev `.env`. Quotes are parsed without AI until `ANTHROPIC_API_KEY` / `OPENAI_API_KEY` / `GEMINI_API_KEY` is set. `OPENROUTER_API_KEY` turns on Jev (optional).
- [ ] **Bot can't see most channels** on "Claesson Notify" (Discord masks them as `___hidden___`). Give the bot View Channel / Read Message History where quotes, imports and stats should work.
- [ ] **Stats need the Presence intent** turned on in the developer portal (game sessions), and the bot must see a channel to count messages there (see "Bot can't see most channels").
- [ ] Old `stat_*` rows are kept forever. Add a retention setting (e.g. delete hourly message buckets older than two years) if the tables grow.
- [ ] Boost MCP server: configured through Sail in `.mcp.json`, but this Claude Code session started before it existed. Restart Claude Code in the project to use its tools.

## Held dependency updates

Dependabot skips these (`ignore` in `.github/dependabot.yml`). Lift a hold when its reason is gone.

- [ ] **Vite+:** `vite-plus`, the `vite` alias in `devDependencies` and the `vite` override in `package.json` must name the same release, and Dependabot only bumps one of them. Bump all three by hand (the release's `vite` dependency names the `@voidzero-dev/vite-plus-core` version).
- [ ] **TypeScript 7:** vue-tsc runs `typescript/lib/tsc`, which TypeScript 7 no longer ships (vuejs/language-tools#6167). Lift once vue-tsc or its successor checks `.vue` files on TypeScript 7.

## Upstream reports (other projects)

- [ ] **pest-plugin-browser:** each browser test run leaves its `playwright run-server` processes behind (about 60, 120 MB each, had piled up in the Sail container). Also, its console capture only wraps `console.log`, so `assertNoConsoleLogs()` misses `console.error` (e.g. Vue hydration errors).
- [ ] **Rector:** removing a redundant `(int)` cast changed `(int) ((int) $id >> 22) % 6` into `(int) $id >> 22 % 6` (different precedence, wrong result). Worked around in `DiscordUser::avatarUrl()` and pinned by a test. Report to rectorphp/rector.
- [ ] **pest-plugin-drift:** `pest --drift` rejected its directory argument in this setup ("only accepts the directory to convert as argument"); the tests were converted by hand.
- [ ] **DiscordPHP:** an invalid token produces an unhandled promise rejection (`InvalidTokenException`) on the gateway lookup, and the client stays alive after critical close codes.
