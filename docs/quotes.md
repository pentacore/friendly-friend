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
5. Fuzzy match (Postgres `pg_trgm` similarity ≥ 0.5) on people and aliases. "Fredrick" finds Fredrik.
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

| How                                                                       | What happens                                                                                                             |
| ------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Right-click a message → **Apps → Save quote**                             | Quotes that message: its author is the speaker, its time is exact. A private preview appears.                            |
| Reply to a message and **@mention the bot**                               | Same, for the replied-to message. The preview arrives by **DM**.                                                         |
| **@bot** with text, e.g. `@bot Bo said "I can fix it" before breaking it` | Speakers, text, context and time are worked out from what you wrote. The preview arrives by DM.                          |
| **`/quote add`** `text` `[who]` `[who_name]` `[context]` `[when]`         | `who` picks a member; `who_name` is for someone not on Discord; `Name: text` lines make a conversation. Private preview. |
| **`/quote random`** `[person]`                                            | Posts a random saved quote from this server, visible to everyone.                                                        |
| **`/quote import`** `channel`                                             | Admins of that server: scan a channel's history for old quotes (see below).                                              |

**Format:** a quote by one person is shown as `"quote text" - Person`, and a conversation as `**Name:** text` lines. Both formats are also understood as **input**, in `@bot` messages, `/quote add text:`, the edit form and channel imports.

**Preview:** shows who each line was matched to ("_(new person)_" for someone new) with three buttons:

- **Save:** publishes the quote and posts it to the server's quotes channel, if one is chosen on `/admin/guilds`.
- **Edit:** opens a form with `Name: text` lines, context and when.
- **Cancel:** discards it.

Only the person who started the quote can use the buttons. Unsaved drafts expire after 15 minutes (`PruneExpiredDrafts`, every minute). A message can only be quoted once. Posts never ping anyone. If the bot can't DM you, it says so in the channel and you can use `/quote add` instead.

**How it works:** Discord requires an answer within 3 seconds. The bot process answers immediately (`App\Discord\Interactions\InteractionAck`: a "thinking…" placeholder, a silent update, or the edit form from the cache via `DraftEditForm`), then `HandleQuoteInteraction` does the work on the queue and replaces the placeholder (`App\Quotes\Discord\QuoteInteractions`). Mentions are handled by `CaptureFromMention`.

## Channel import (`/quote import` or `quotes:import`)

`ImportChannelQuotes` walks the channel's history backwards (5 pages of 100 per job run, resumable), skips bots, links, empty messages and messages already quoted, and turns the ones that look like quotes into quotes with status **`pending_review`**. Their submitter is the message author, and their time is the stated time or the day it was posted. They wait for an admin on the website (Phase 5).

Is it a quote? `App\Ai\QuoteDetector` decides, in this order:

1. **Jev** (TypeSafe's fast structured-decision model, through laravel/ai `Classification`), if `TYPESAFE_API_KEY` is set (waitlisted access). It's cheap and gives calibrated probabilities (threshold 0.5).
2. A cheap LLM yes/no (`QuoteDetectorAgent`), if a quote AI provider key is set.
3. Heuristics: `Name: text`, quotation marks, "said".

```bash
vendor/bin/sail artisan quotes:import <server id> <channel id> --as=<your discord user id>
```

## On the website: visibility, permissions, search

- **Visible:** published quotes of the servers the user gets in through (`Quote::visibleTo(AccessSnapshot)`). Owners see every enabled server.
- **Permissions:** `App\Quotes\QuoteRules` is shared by `QuotePolicy` and `QuoteResource`.
    - view: published, and in a visible server
    - update: the submitter, or that server's admins
    - delete and review imports: that server's admins
- **`AccessSnapshot`:** computes the user's visible and moderated servers once per request (cached on the Request), so lists don't re-check access per quote.
- **Search:** `quotes.search_text` (speakers, lines and context) is rebuilt on every content change (`Quote::refreshSearchText`). `search_vector` is a generated `tsvector` (`simple` config, which suits mixed Swedish and English) with a GIN index.

## Editing and removing on the website

- **Edit** (quote page, the submitter or that server's admins): change the lines, context and date. `EditQuote` rewrites the lines, gives every speaker a person (`EnsureLinePeople`, the same as saving in Discord), rebuilds the search text and records a revision, which shows under _Edit history_.
- **Remove** (that server's admins): `RemoveQuote` sets the status to `removed`. The quote disappears everywhere but isn't deleted, so its source message can't be quoted again by accident.
