# Phase 4: Quotes in Discord Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Members of enabled servers capture quotes from Discord in four ways (reply plus @bot, the "Save quote" context menu, `/quote`, and @bot with free text). The AI tidies them up. The submitter previews, edits or cancels privately, and saved quotes are posted to the server's quotes channel. Also `/quote random` and an admin channel import that creates quotes for review.

**Architecture:**

- **Interactions:** the bot process acknowledges every interaction itself, inside Discord's 3-second limit, with a non-blocking DiscordPHP `post`. That's the single allowed Discord call in the bot. The type of acknowledgement is decided by a pure `InteractionAck` from the payload: deferred ephemeral reply for commands, deferred update for buttons and form submissions, or the edit form (modal) built from a Redis-cached draft. The router then queues `HandleInteraction`, which does the work and edits the original response over REST.
- **Mentions:** `MESSAGE_CREATE` events that mention the bot are queued as `CaptureFromMention`. The preview is sent by DM, because a normal message can't be private.
- **Drafts:** every path produces a `draft` quote with lines. Speakers are either resolved people or "new person" names, and people are only created when the quote is saved. Drafts expire after 15 minutes.
- **AI:** `App\Ai\QuoteNormalizer` is an interface. `AiQuoteNormalizer` (a laravel/ai structured-output agent with provider failover) falls back to `PlainQuoteNormalizer`, a deterministic parser of `Name: text` lines, when no provider key is set or the AI fails.

**Spec:** §4 (quotes and people), §5, §5.1, §14 (interaction acknowledgement).

## Global Constraints

- Quotes carry `guild_id`. Only users with access through that guild (`GuildAccess`) can capture. Admins of that guild (or owners) can import.
- Snowflakes are strings. Lines have a `position`. Every save or edit writes a `quote_revisions` snapshot.
- AI failure never blocks capture.
- The router doesn't touch the DB. Its only side effects are the interaction acknowledgement and cache reads for modal prefill.
- Postgres `pg_trgm` for fuzzy name matching (similarity ≥ 0.5).

## Slices (one commit each, with tests, docs and guidelines)

1. **Schema and domain:**
    - tables `people`, `person_aliases`, `quotes`, `quote_lines`, `quote_revisions`, `quote_imports`, and `guilds.channels` / `guilds.quotes_channel_id`
    - models and factories, enums `QuoteStatus`, `QuoteSource`, `SaidAtPrecision`
    - actions `ResolvePerson`, `DraftQuote`, `UpdateDraft`, `ConfirmQuote`, `CancelDraft`
2. **Normalizer:** `QuoteNormalizer` (`normalize`, `classifyBatch`), `PlainQuoteNormalizer`, `AiQuoteNormalizer` (agent, failover, timeout), and `config/quotes.php` (provider list from env, only providers with keys).
3. **Interactions infrastructure:**
    - `QuoteCommands` definitions and `RegisterGuildCommands` (bulk PUT per enabled guild, run on register, enable, and `discord:register-commands`)
    - `InteractionAck`, `InteractionAcknowledger` (bot: DiscordPHP; tests: fake) and `DraftPreviewCache`
    - router branches for `INTERACTION_CREATE` and `MESSAGE_CREATE` (bot mentioned)
4. **Capture flows:**
    - `HandleInteraction` (`/quote`, "Save quote", buttons `quote:save|edit|cancel:{id}`, modal `quote:modal:{id}`) and `CaptureFromMention`
    - preview rendering
    - `DiscordClient` additions: edit the original response, send a DM, send a message with components
    - posting to `quotes_channel_id`, and `/quote random`
5. **Expiry and import:** scheduled `PruneExpiredDrafts`, `/quote import` and `quotes:import` with the `ImportChannelQuotes` job (paged history, `classifyBatch`, `pending_review`), and a quotes-channel picker on the Servers page.
6. **Live test** against the real server, then docs (`docs/quotes.md`, bot, admin) and guidelines. Push and check CI.
