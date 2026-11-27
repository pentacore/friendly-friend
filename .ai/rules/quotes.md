---
paths:
    - 'app/Quotes/**'
    - 'app/Ai/**'
    - 'app/Models/Quote*.php'
    - 'app/Models/Person*.php'
---

# Quotes and AI

## Domain

- A quote is made of ordered `quote_lines` (speaker plus text), so conversations work.
- **Display format:** one speaker is `"text" - Person`, a conversation is `Name: text` lines; an indented line continues the previous speaker's line (keeps line breaks inside a line). This holds in Discord, on the web and in the edit form, and the parser accepts both formats as input.
- Speakers are resolved only by `Actions\ResolvePerson` (Discord user, alias, name, server member, `pg_trgm` fuzzy match ≥ 0.5, Jev choosing when several people match, otherwise a new person). The preview always shows the match.
- In drafts a line may have no `person_id`. People are created only by `EnsureLinePeople`, when a quote is saved (`ConfirmQuote`) or edited (`EditQuote`), so cancelled drafts never leave stray people.
- Every capture path is draft, then a private preview, then Save/Edit/Cancel. Drafts expire after 14 minutes (`PruneExpiredDrafts`), just inside Discord's 15-minute interaction token, so the private preview can be edited to "expired": the latest interaction token is kept (encrypted) on the draft, or the DM's message for mention captures, and cleared once saved. Imports and watched channels become `pending_review` for admins (`CaptureChannelMessage`); a watched channel's quotes collect in its standing import (status `watching`, `QuoteImport::WATCHING`), so one review screen covers both. A server can opt in to publishing picked-up quotes straight away (`publish_watched_quotes`): they go through `ConfirmQuote` with the poster as editor, never around it.
- A Discord message can only be quoted once (`source_message_id` is unique). Removing a quote sets `removed`; it isn't deleted, stays visible to that server's admins, and `RestoreQuote` puts it back (`QuoteRules::canRestore`).
- Search quotes only through the `Quote::search()` scope (feed, person pages, Discord), never with ad-hoc `ilike` or `websearch_to_tsquery`. People's names are matched live through `quote_lines.person_id`, so renames and new nicknames need no reindexing.
- Every save and edit writes a `quote_revisions` snapshot (`RecordRevision`), and every content change rebuilds `search_text` (`Quote::refreshSearchText`).
- People are shared across servers, so managing them (aliases, Discord links, merges via `MergePeople`) is behind the general `admin` gate. Aliases are unique case-insensitively, and a Discord account belongs to at most one person.
- Permissions live in `QuoteRules` (view / update / delete / review), shared by `QuotePolicy` and `QuoteResource`.

## AI

- Inject `App\Ai\QuoteNormalizer`, never an agent. `AiQuoteNormalizer` tries the configured providers that have keys (laravel/ai failover) and falls back to `PlainQuoteNormalizer`. **AI failure never blocks capture.**
- Certain facts win over the AI: a message's author and timestamp always override what the AI says.
- The AI keeps the wording (quotes are deliberately unfair). It only fixes formatting.
- Channel imports detect quotes with `QuoteDetector`: Jev if configured, then a cheap LLM, then heuristics.
- **Jev goes only through OpenRouter**, via `App\Ai\Jev` (`isAvailable()` = `OPENROUTER_API_KEY` set, `ask()` = one laravel/ai Classification question on `Lab::OpenRouter`). Never call `Classification` or `Lab::TypeSafe` directly. Every Jev use has a non-Jev fallback, also when it throws, so Jev only ever improves a decision.
- An @mention (not a reply) goes through `MentionIntentClassifier`: capture unless Jev is ≥ 0.6 sure it's a random-quote or help request. Capture is the default because its preview can be cancelled; a reply-mention always captures.
