# Phase 5: Quotes on the Website Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Everyone with access browses, searches, votes on and ranks the quotes of the servers they belong to. Submitters and admins edit with history, and admins remove quotes, manage people and review channel imports. Dark mode is verified with browser tests.

**Architecture:**

- **Visibility:** published quotes of the guilds the user can access (`GuildAccess::accessibleGuildIds`, owners see all), in the scope `Quote::visibleTo(User)`.
- **Permissions:** `QuotePolicy` (view, update: submitter or that guild's admin; delete: that guild's admin) and `PersonPolicy`.
- **Search:** `quotes.search_text` is rebuilt by every content change, plus a generated `tsvector` (`simple` config, which suits mixed Swedish and English) with a GIN index.
- **Votes:** in `quote_votes`. `score` is cached on `quotes`. Changes broadcast `QuoteScoreChanged` on the private channel `guilds.{guildId}.quotes`.
- **This-or-that:** `quote_matchups` plus Elo (K = 32) on `quotes.elo` and `matchup_count`. Pairs are drawn from the least-played quotes.
- **Frontend:** Inertia v3. The feed uses `Inertia::scroll` with `<InfiniteScroll>`, votes use `router.optimistic`, and live scores come from Echo (`useEcho`). Every prop type comes from Typefinder resources and `#[TypefinderPage]`.
- **Shared `QuoteCard`:** a single speaker shows as `"text" - Person`, a conversation as `Name: text` lines.

## Slices (each a commit, with tests, docs and the ROADMAP)

1. **Schema and visibility:** `quote_votes`, `quote_matchups`, `quotes.search_text` and `search_vector`, `Quote::visibleTo`, `QuotePolicy`, `QuoteResource`, `PersonSummary`, and `RefreshSearchText` hooked into `WriteDraftLines` and `EditQuote`.
2. **Feed and quote page:** `/quotes` (filters for server, person and search, infinite scroll), `/quotes/{quote}` (lines, meta, source link, revisions), `/quotes/random`, and the nav.
3. **Editing:** `EditQuote` action (lines, context, when, revision) with a form on the quote page, and removal for admins.
4. **Voting:** `RecordVote` (toggle and switch), `QuoteScoreChanged` broadcast, channel auth, optimistic vote buttons and live updates.
5. **This-or-that and top lists:** `RecordMatchup`, `/this-or-that`, `/top` (all-time, this month, Elo, most quoted, top submitters).
6. **People:** `/people/{person}` (their quotes and aliases), plus admin tools to merge people, add and remove aliases, and link or unlink a Discord user.
7. **Import review:** `/admin/imports` (imports per guild, with progress) and `/admin/imports/{import}` (pending quotes next to their source link: approve, edit, reject, approve all).
8. **Dashboard and dark mode:** the dashboard shows a random quote, recent quotes and links. Pest browser tests (Playwright in Sail and CI) render the main pages in light and dark.
