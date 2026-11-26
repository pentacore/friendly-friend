# friendly-friend: domain rules (must always hold)

## Identity

- `discord_members` holds **every** member of the server (synced by the bot), whether or not they ever logged in. `users` are members who have logged in to the website.
- `people` are anyone who can be quoted. `discord_member_id` is **nullable**, because people outside the server can be quoted. Nicknames are stored in `person_aliases`.

## Quotes

- A quote is made of ordered `quote_lines`, each with a person and text, so a back-and-forth exchange is supported.
- Every capture path ends in draft → private preview → confirm. Drafts expire after 15 minutes. Channel imports become `pending_review`, and an admin approves them.
- AI failure must never block capture: if the primary provider fails, try the fallback provider, then use the raw text.
- Every edit writes a `quote_revisions` snapshot. Only the submitter and admins can edit (`QuotePolicy`).
- Quotes are allowed to be unfair by design. **Opting out of tracking never hides or blocks quotes.**

## Stats and privacy

- Stats store **counts and sessions only**. Never store message content for stats.
- Opt-out is checked **twice**: in the bot before an event is queued, and again in the job. Opting out deletes all existing `stat_*` rows for that member (`PurgeMemberStats`). Opting back in only counts new activity.
- Stats writes are idempotent upserts keyed by their time bucket.

## Access

- Login is Discord OAuth only (Socialite, scopes `identify` and `guilds.members.read`). There are no passwords, no email and no registration. Fortify is not installed, so don't add it back.
- Every access decision goes through `App\Access\GuildAccess` (`allows()`, `isAdmin()`). Don't compare role IDs anywhere else. Use the `admin` gate (`Gate::allows('admin')`, `@can('admin')`) for admin-only features.
- Allowed means in the guild **and** (a role in `DISCORD_ALLOWED_ROLE_IDS`, **or** an admin role, **or** the allowed list is empty). Admin means in the guild with a role in `DISCORD_ADMIN_ROLE_IDS`.
- Every app route uses `['auth', 'guild.access']`. The middleware re-checks the stored member row on each request and logs out anyone who has lost access.
- Discord IDs (snowflakes) are **strings** everywhere: DB, PHP, TS.
- Members without access are still stored in `discord_members`. Only allowed members get a `users` row.
- The frontend user is the shared prop `auth.user` (`{id, discord_id, name, avatar_url, is_admin}`). Never send `discord_members` or `users` models straight to the frontend.
