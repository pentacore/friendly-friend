# Quotes

Quotes are captured in Discord, shown and voted on on the website (Phase 5), and belong to the server they came from (`quotes.guild_id`). People and quotes are one shared pool across servers.

## Data model

| Table                      | What                                                                                                                                                                                                                                                                                                                               |
| -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `people`                   | Anyone who can be quoted. `discord_user_id` is null for people outside the servers. `slug` for URLs.                                                                                                                                                                                                                               |
| `person_aliases`           | Other names a person goes by ("Marre"). Unique, case-insensitive.                                                                                                                                                                                                                                                                  |
| `quotes`                   | `status` (`draft` → `published`; `pending_review` for imports; `removed`), `source`, `context`, `said_at` with precision (`exact`/`day`/`approximate`), source message (unique: a message can only be quoted once), `submitted_by_discord_user_id`, `raw_input`, `ai_output` (debugging), `score`/`elo`/`matchup_count` (Phase 5). |
| `quote_lines`              | Ordered lines: `speaker_name`, `text`, `person_id`. In a **draft**, `person_id` may still be null: `speaker_discord_user_id` set means "this Discord user", neither means "a new person". People are only created when the quote is saved.                                                                                         |
| `quote_revisions`          | A full snapshot (lines, context, said_at) on every save and edit, with the editor.                                                                                                                                                                                                                                                 |
| `quote_imports`            | Channel backfills: cursor, progress, status.                                                                                                                                                                                                                                                                                       |
| `guilds.quotes_channel_id` | Where saved quotes are posted. `guilds.channels` caches the text channels for the picker.                                                                                                                                                                                                                                          |

## Who said it: `App\Quotes\Actions\ResolvePerson`

Checked in order:

1. Known Discord user (message author or mention) → their person, or a link to them that becomes a person on save.
2. Alias, case-insensitive.
3. A person's display name, case-insensitive.
4. A member **of the quote's server**, by server nickname, global display name or username.
5. Fuzzy match (Postgres `pg_trgm` similarity ≥ 0.5) on people and aliases. "Fredrick" finds Fredrik. When **several people** match (up to 5), **Jev** picks one, or "someone else" (a new person). It sees the name as written, the line, each candidate's aliases and whether they're in this server. Without Jev, or if it fails, the closest match wins.
6. Otherwise a **new person** with that name.

The preview always shows who each line was matched to, so a wrong guess is caught before saving.

## Lifecycle (`App\Quotes\Actions`)

| Action            | Does                                                                                                                                              |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DraftQuote`      | Creates the draft with resolved lines. A source message that's already saved throws `AlreadyQuotedException`; an unsaved draft of it is replaced. |
| `UpdateDraft`     | Applies the edit form to a draft.                                                                                                                 |
| `ConfirmQuote`    | Creates any missing people (reusing exact-name matches), publishes, records the first revision.                                                   |
| `CancelDraft`     | Deletes a draft.                                                                                                                                  |
| `RecordRevision`  | Snapshot, used by every save and edit.                                                                                                            |
| `WriteDraftLines` | Shared by draft and update: replaces lines, resolving each speaker.                                                                               |

## AI cleanup (`App\Ai`)

`QuoteNormalizer` (bound to `AiQuoteNormalizer`) turns a `CaptureInput` into a `NormalizedQuote` (lines, context, said_at with precision).

- **AI:** `Agents\QuoteNormalizerAgent` (laravel/ai structured output, cheapest model, temperature 0) keeps the wording, because the quotes are meant to be unfair, splits dialogue into lines and works out the time. It answers in the input's language (Swedish or English).
- **Providers:** `QUOTES_AI_PROVIDERS` (default `anthropic,openai,gemini`), tried in order. Only those with a key in `.env` are used (`ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `GEMINI_API_KEY`). Timeout: `QUOTES_AI_TIMEOUT` (15 s).
- **Fallback:** `PlainQuoteNormalizer` is used with no keys, on AI errors, or when the AI answer has no lines. It handles `"text" - Person`, `Name: text` dialogues, `Name said "text" …`, `today`/`yesterday`/`2026-10-31`, and keeps vague times ("last summer") as context.
- **Certain facts win:** for a quoted message, the author and the message time always override the AI.
- The raw AI answer is stored on `quotes.ai_output` for debugging.

## Capturing quotes in Discord

Members with access through an **enabled** server can do all of this. The bot registers its commands in every enabled server: on connect, when a server is enabled, or by hand with `vendor/bin/sail artisan discord:register-commands`.

| How                                                                       | What happens                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| ------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Right-click a message → **Apps → Save quote**                             | Quotes that message: its author is the speaker, its time is exact. A private preview appears.                                                                                                                                                                                                                                                                                                                                                                          |
| Reply to a message and **@mention the bot**                               | Same, for the replied-to message. The preview arrives by **DM**.                                                                                                                                                                                                                                                                                                                                                                                                       |
| **@bot** with text, e.g. `@bot Bo said "I can fix it" before breaking it` | Speakers, text, context and time are worked out from what you wrote. The preview arrives by DM. With Jev, asking for **a random quote** (`@bot random quote`, `@bot a quote from @Bo`, or a name: `@bot a quote from Uncle Rolf`, `ett citat från Rolle`, matched like a speaker by `ResolvePerson` and ignored if nobody matches) replies with one in the channel (by DM if the server keeps random quotes private), and asking for **help** DMs what the bot can do. |
| **`/quote add`** `text` `[who]` `[who_name]` `[context]` `[when]`         | `who` picks a member; `who_name` is for someone not on Discord; `Name: text` lines make a conversation. Private preview.                                                                                                                                                                                                                                                                                                                                               |
| **`/quote random`** `[person]`                                            | Posts a random saved quote from this server, visible to everyone (or only to you, if the server chose that).                                                                                                                                                                                                                                                                                                                                                           |
| **`/quote search`** `text [person]`                                       | Only you see the results: how many of this server's quotes match (`Quote::search`, best match first), the best five with links, and a link to all of them on the website.                                                                                                                                                                                                                                                                                              |
| **`/quote import`** `channel`                                             | Admins of that server: scan a channel's history for old quotes (see below).                                                                                                                                                                                                                                                                                                                                                                                            |

**Format:** a quote by one person is shown as `"quote text" - Person`, and a conversation as `**Name:** text` lines. Both formats are also understood as **input**, in `@bot` messages, `/quote add text:`, the edit form and channel imports.

**Preview:** shows who each line was matched to ("_(new person)_" for someone new) with three buttons:

- **Save:** publishes the quote and posts it to the server's quotes channel, if one is chosen on `/admin/guilds`.
- **Edit:** opens a form with `Name: text` lines, context and when.
- **Cancel:** discards it.

In the edit form a conversation is `Name: text` lines; an indented line continues the previous speaker's line, which is how line breaks inside a line are kept. Only the person who started the quote can use the buttons. Unsaved drafts expire after 14 minutes by default (`PruneExpiredDrafts`, every minute; owners can shorten it on the Settings page), and their preview changes to "This draft expired before it was saved" with no buttons (just inside Discord's 15-minute window for editing an interaction reply). A message can only be quoted once. Posts never ping anyone. If the bot can't DM you, it says so in the channel and you can use `/quote add` instead.

**How it works:** Discord requires an answer within 3 seconds. The bot process answers immediately (`App\Discord\Interactions\InteractionAck`: a "thinking…" placeholder, a silent update, or the edit form from the cache via `DraftEditForm`), then `HandleQuoteInteraction` does the work on the queue and replaces the placeholder (`App\Quotes\Discord\QuoteInteractions`). Mentions are handled by `CaptureFromMention`.

## Channel import (`/quote import` or `quotes:import`)

The per-message step (detect, normalise, store as `pending_review`) is `Quotes\Actions\CaptureChannelMessage`, shared with watched channels.

`ImportChannelQuotes` walks the channel's history backwards (5 pages of 100 per job run, resumable), skips bots, links, empty messages and messages already quoted, and turns the ones that look like quotes into quotes with status **`pending_review`**. Their submitter is the message author, and their time is the stated time or the day it was posted. They wait for an admin on the website: **Imports** in the sidebar (`/admin/imports`) lists each import with its progress (polled every 3 s while it runs). The review page shows the found quotes next to a link to the original message, with **Approve** (`ConfirmQuote`: creates people and publishes, keeping the original date), **Not a quote** (`removed`, so it's never imported again), **Edit**, and **Approve all**. Only that server's admins (and owners) can review.

Is it a quote? `App\Ai\QuoteDetector` decides, in this order:

1. **Jev** (see below), if `OPENROUTER_API_KEY` is set. It's cheap and gives calibrated probabilities (threshold 0.5).
2. A cheap LLM yes/no (`QuoteDetectorAgent`), if a quote AI provider key is set.
3. Heuristics: `Name: text`, quotation marks, "said".

### Jev (through OpenRouter)

Jev is TypeSafe's fast, cheap decision model. Every Jev call goes through `App\Ai\Jev`, which asks laravel/ai's **OpenRouter** provider (model `~typesafe/jev-latest`, OpenRouter's decisions endpoint), so Jev is on whenever `OPENROUTER_API_KEY` is set. It's used for three decisions, and each falls back to what happens without it when it's off or fails:

| Where                                | Question                                                                | Without Jev          |
| ------------------------------------ | ----------------------------------------------------------------------- | -------------------- |
| `QuoteDetector` (imports, watched)   | Is this message a quote?                                                | LLM, then heuristics |
| `MentionIntentClassifier` (`@bot …`) | Save a quote, a random quote, or help? Anything but "save" needs ≥ 0.6. | Always save          |
| `ResolvePerson`                      | Which of several fuzzy matches is the speaker?                          | Closest match        |

```bash
vendor/bin/sail artisan quotes:import <server id> <channel id> --as=<your discord user id>
```

## Watched channels (quotes posted without the bot)

For when people post a quote as a normal message and forget the bot. On the Servers page, an owner ticks **Watch for posted quotes in** for one or more channels (it can be the channel the bot posts saved quotes in; the bot ignores its own messages).

- Each new message there (not from a bot, not mentioning the bot, which already starts a capture) goes through `QuoteDetector`, like a channel import. Messages that look like quotes become `pending_review` quotes with source `watched_channel`, and the bot reacts 📝 to the message so people can see it was noticed.
- Each watched channel has a standing import (status `watching`) that collects them, so they're reviewed on **Imports** like any import: approve, edit, not a quote, approve all. Unwatching a channel finishes its import.
- **Publish them straight away** (off by default, per server, on the Servers page): picked-up quotes skip review and are published at once, credited to the person who posted them (`guilds.publish_watched_quotes`, checked in `PickUpPostedQuote`). They aren't posted to the quotes channel, like approved imports.
- The bot decides from a cached list of watched channels (`Quotes\WatchedChannels`, refreshed on save and when the bot starts); `PickUpPostedQuote` checks the database again.
- Every message in a watched channel is classified. With an AI key that's one cheap AI call per message, so watch quote channels, not busy chat.

## On the website: visibility, permissions, search

- **Visible:** published quotes of the servers the user gets in through (`Quote::visibleTo(AccessSnapshot)`). Owners see every enabled server.
- **Permissions:** `App\Quotes\QuoteRules` is shared by `QuotePolicy` and `QuoteResource`.
    - view: published, and in a visible server
    - update: the submitter, or that server's admins
    - delete and review imports: that server's admins
- **`AccessSnapshot`:** computes the user's visible and moderated servers once per request (cached on the Request), so lists don't re-check access per quote.
- **Search:** `quotes.search_text` (speakers, lines and context) is rebuilt on every content change (`Quote::refreshSearchText`). `search_vector` is a generated `tsvector` (`simple` config, which suits mixed Swedish and English) with a GIN index. Every search goes through `Quote::search($text)`: each word must match as a prefix (`kanel` finds _kanelbulle_ while typing), `-word` leaves quotes with it out, and a word also matches the current name or a nickname of a person on the quote, so renamed, merged or nicknamed people are found under any of their names. Only letters and digits are kept, so any input is safe.
- **Feed order and filters:** with a search the feed sorts by **best match** (`orderByRelevance`, `ts_rank` over the matched words), otherwise by **newest**; **top score** is the third choice (`sort` in the query string, default left out of the URL). Matched words are marked in the quote cards (`lib/searchHighlight.ts`, the same word rules as the server). A **person** picker lists everyone with a quote you can see (shown when there are two or more).

## Editing and removing on the website

Removed quotes stay in the database. That server's admins still see them (with a "Removed" banner on the quote page) and find them all under **Admin → Removed quotes**, where **Restore quote** puts them back with their votes, Elo and history.

- **Edit** (quote page, the submitter or that server's admins): change the lines, context and date. `EditQuote` rewrites the lines, gives every speaker a person (`EnsureLinePeople`, the same as saving in Discord), rebuilds the search text and records a revision, which shows under _Edit history_.
- **Remove** (that server's admins): `RemoveQuote` sets the status to `removed`. The quote disappears everywhere but isn't deleted, so its source message can't be quoted again by accident.

## Voting (live)

- 👍/👎 on every quote card and quote page. Clicking the same vote again removes it, and clicking the other one switches. `RecordVote` recomputes the cached `quotes.score` from `quote_votes` (one vote per user per quote).
- The buttons update straight away (optimistic) and roll back if the request fails. The request is `POST /quotes/{id}/vote` (JSON, Inertia `useHttp`), answered with `VoteResultResource`.
- **Live:** `QuoteScoreChanged` is broadcast on the private channel `guilds.{id}.quotes` (Reverb), authorised for anyone who can see that server's quotes. Pages call `provideLiveQuoteScores()` once (one listener per server), and `VoteButtons` read the shared live score.

## This or that, and top lists

- **This or that** (`/this-or-that`) shows two quotes; pick the funnier one with a click or the ← / → keys. `RecordMatchup` updates both quotes' **Elo** (K = 32, start 1000) and `matchup_count`. Pairs favour quotes with the fewest matchups, plus a little randomness.
- **Top lists** (`/top`):
    - funniest of all time and this month, by score
    - this-or-that champions, by Elo, after at least 3 matchups
    - most quoted people
    - top quote savers

    All lists only cover quotes the user can see.

## Dashboard

After login, `/dashboard` (`DashboardController`) shows one random quote large, with voting, **Another one** (reloads only that quote) and a link to it. Below are shortcuts to this or that and the top lists, and the five latest quotes. With no quotes yet, it explains how to save the first one.
