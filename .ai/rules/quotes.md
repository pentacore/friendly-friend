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
- **Display format:** one speaker is `"text" - Person`, a conversation is `Name: text` lines. This holds in Discord, on the web and in the edit form, and the parser accepts both formats as input.
- Speakers are resolved only by `Actions\ResolvePerson` (Discord user, alias, name, server member, `pg_trgm` fuzzy match ≥ 0.5, otherwise a new person). The preview always shows the match.
- In drafts a line may have no `person_id`. People are created only by `EnsureLinePeople`, when a quote is saved (`ConfirmQuote`) or edited (`EditQuote`), so cancelled drafts never leave stray people.
- Every capture path is draft, then a private preview, then Save/Edit/Cancel. Drafts expire after 15 minutes (`PruneExpiredDrafts`). Imports and watched channels become `pending_review` for admins (`CaptureChannelMessage`); a watched channel's quotes collect in its standing import (status `watching`, `QuoteImport::WATCHING`), so one review screen covers both.
- A Discord message can only be quoted once (`source_message_id` is unique). Removing a quote sets `removed`; it isn't deleted.
- Every save and edit writes a `quote_revisions` snapshot (`RecordRevision`), and every content change rebuilds `search_text` (`Quote::refreshSearchText`).
- People are shared across servers, so managing them (aliases, Discord links, merges via `MergePeople`) is behind the general `admin` gate. Aliases are unique case-insensitively, and a Discord account belongs to at most one person.
- Permissions live in `QuoteRules` (view / update / delete / review), shared by `QuotePolicy` and `QuoteResource`.

## AI

- Inject `App\Ai\QuoteNormalizer`, never an agent. `AiQuoteNormalizer` tries the configured providers that have keys (laravel/ai failover) and falls back to `PlainQuoteNormalizer`. **AI failure never blocks capture.**
- Certain facts win over the AI: a message's author and timestamp always override what the AI says.
- The AI keeps the wording (quotes are deliberately unfair). It only fixes formatting.
- Channel imports detect quotes with `QuoteDetector`: Jev (TypeSafe classification) if configured, then a cheap LLM, then heuristics.
