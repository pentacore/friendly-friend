---
paths:
    - 'app/Access/**'
    - 'app/Http/Middleware/**'
    - 'app/Policies/**'
    - 'app/Providers/AppServiceProvider.php'
---

# Access

- Login is Discord OAuth only (Socialite, scopes `identify`, `guilds` and `guilds.members.read`). There are no passwords, email or registration, and Fortify is not installed. Don't add it back.
- Every access decision goes through `App\Access\GuildAccess` (`allows`, `isAdmin`, `isOwner`, `allowsInGuild`, `isAdminInGuild`, `visibleGuildIds`, `adminGuildIds`). Don't compare role IDs anywhere else.
- **Owners** = `DISCORD_OWNER_IDS` (user IDs): always allowed, always admin, see every enabled server, and the only ones who manage servers.
- **Allowed** = owner, or a still-present member of at least one _enabled_ server whose own settings let them in (`allow_all_members`, an allowed role, or an admin role). Role lists are per server.
- **Admin** = owner, or an admin role in an enabled server. Moderation is per server: use `isAdminInGuild` / `AccessSnapshot::moderates($guildId)`.
- Gates: `admin` (Horizon, moderation) and `manage-guilds` (owners only).
- Every app route uses `['auth', 'guild.access']`. The middleware re-checks access on each request and logs out anyone who has lost it.
- **Lists and resources use `AccessSnapshot::forRequest($request)`**, which is computed once per request and cached on the Request, instead of calling `GuildAccess` per item. Policies delegate to rule classes (e.g. `App\Quotes\QuoteRules`) that take the snapshot, so a policy and a resource can never disagree.
- The frontend user is the shared prop `auth.user` (`{id, discord_id, name, avatar_url, is_admin, is_owner}`). Never send `discord_users`, `guild_members` or `users` models straight to the frontend.
