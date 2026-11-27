# friendly-friend: domain rules (must always hold)

## Identity (multiple servers)

- The bot can be in **several Discord servers** (`guilds`). Data is one shared pool tagged by server: quotes carry a `guild_id`, stats are keyed by `guild_id` + `discord_user_id`. Never build per-server silos.
- `discord_users` is the person (one row across all servers). `guild_members` is their membership of one server (nickname, roles, `in_guild`). Every member of every enabled server is stored, whether or not they log in. `users` are people who have logged in.
- `guilds` are registered by the bot. New servers start **disabled** (`enabled=false`), and the bot's jobs ignore disabled servers. `bot_present=false` means the bot was removed.
- `people` are anyone who can be quoted. `discord_user_id` is **nullable**, because people outside the servers can be quoted. Nicknames are stored in `person_aliases`.

## Quotes

- A quote is made of ordered `quote_lines`, each with a speaker and text, so a back-and-forth exchange is supported. In drafts a line may have no `person_id` yet (`speaker_discord_user_id` or just `speaker_name`). People are only created by `ConfirmQuote`, so cancelled drafts never leave stray people.
- Speakers are always resolved with `App\Quotes\Actions\ResolvePerson` (Discord user, alias, name, server member, fuzzy match, new). Don't match names anywhere else.
- A Discord message can only be quoted once (`quotes.source_message_id` is unique). `DraftQuote` replaces an unsaved draft of it and refuses a saved one (`AlreadyQuotedException`).
- Quote state changes go through `App\Quotes\Actions` (`DraftQuote`, `UpdateDraft`, `ConfirmQuote`, `CancelDraft`, `RecordRevision`). Details: `docs/quotes.md`.
- Every capture path ends in draft → private preview → confirm. Drafts expire after 15 minutes. Channel imports become `pending_review`, and an admin approves them.
- AI failure must never block capture. `App\Ai\AiQuoteNormalizer` tries the configured providers that have keys (laravel/ai failover, `config/quotes.php`) and falls back to `PlainQuoteNormalizer`. Inject the `QuoteNormalizer` interface, never the agent. Certain facts (message author, message time) always win over the AI's answer.
- Every edit writes a `quote_revisions` snapshot. Only the submitter and admins can edit (`QuotePolicy`).
- Quotes are allowed to be unfair by design. **Opting out of tracking never hides or blocks quotes.**

## Stats and privacy

- Stats store **counts and sessions only**. Never store message content for stats.
- Opt-out is checked **twice**: in the bot before an event is queued, and again in the job. Opting out deletes all existing `stat_*` rows for that member (`PurgeMemberStats`). Opting back in only counts new activity.
- Stats writes are idempotent upserts keyed by their time bucket.

## Access

- Login is Discord OAuth only (Socialite, scopes `identify`, `guilds` and `guilds.members.read`). There are no passwords, no email and no registration. Fortify is not installed, so don't add it back.
- Every access decision goes through `App\Access\GuildAccess` (`allows()`, `isAdmin()`, `isOwner()`, `accessibleGuildIds()`). Don't compare role IDs anywhere else.
- **Owners** = `DISCORD_OWNER_IDS` (user IDs). They are always allowed, always admin, and are the only ones who can manage servers.
- **Allowed** = owner, **or** a still-present member of at least one _enabled_ server whose own settings let them in (`allow_all_members`, a role in its `allowed_role_ids`, or a role in its `admin_role_ids`). Role lists are per server, so a role only counts in its own server.
- **Admin** = owner, or an admin role in an enabled server.
- Gates: `admin` (Horizon, moderation) and `manage-guilds` (owners only: `/admin/guilds`).
- Every app route uses `['auth', 'guild.access']`. The middleware re-checks access on each request and logs out anyone who has lost it.
- Discord IDs (snowflakes) are **strings** everywhere: DB, PHP, TS. Watch out: PHP turns numeric-looking array **keys** into ints (`['99' => 'Bo']` has key `99`, and `array_flip()` yields int values). Cast with `(string)` when IDs come back out of array keys.
- The frontend user is the shared prop `auth.user` (`{id, discord_id, name, avatar_url, is_admin, is_owner}`). Never send `discord_users`, `guild_members` or `users` models straight to the frontend. Use resources and page props.
- Postgres `jsonb` reorders object keys. Never rely on key order in stored JSON, and compare with `toEqual`, not `toBe`, in tests.
- Values read back from the Redis cache may change type (numbers come back as strings). Store strings and parse them on read.
