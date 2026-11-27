# Login and access

Login is **Discord OAuth only**. There are no passwords, no registration and no email (Fortify was removed). The bot can be in several Discord servers ("guilds"), and access is decided per server on [`/admin/guilds`](admin.md).

## Flow

1. `GET /login` shows the "Log in with Discord" page (`auth/Login`).
2. `GET /auth/discord/redirect` sends the browser to Discord with the scopes `identify`, `guilds` and `guilds.members.read`.
3. `GET /auth/discord/callback` runs `App\Access\Actions\LoginWithDiscord`:
    - records the Discord account (`discord_users`)
    - lists the user's servers (`GET /users/@me/guilds`)
    - for each **enabled** server they're in, reads their membership (`GET /users/@me/guilds/{id}/member`) into `guild_members`. Memberships of enabled servers they're no longer in are marked `in_guild = false`.
    - no access, and not in any enabled server → `error=not-member`
    - no access, but a member somewhere → `error=missing-role` (the memberships are kept, because quotes and stats need everyone)
    - otherwise a `users` row is found or created and logged in with "remember me"
4. `POST /logout`.

## Who gets in

`App\Access\GuildAccess`:

- **Owners** (`DISCORD_OWNER_IDS`, Discord user IDs): always allowed, always admin, and the only ones who can manage servers.
- **Allowed**: an owner, or a current member of at least one **enabled** server where:
    - "let every member log in" is on, or
    - they have one of that server's allowed roles, or
    - they have one of that server's admin roles.

    Roles only count in their own server.

- **Admin** (`admin` gate, e.g. Horizon outside `local`): an owner, or an admin role in an enabled server.
- **Manage servers** (`manage-guilds` gate): owners only.

## Staying in

The `guild.access` middleware (`App\Http\Middleware\EnsureGuildAccess`) runs on every app route together with `auth` and re-checks `GuildAccess::allows()`. Someone who left their last server, lost their roles, or whose only server was disabled is logged out on their next request (`error=access-revoked`).

Memberships are kept current by every login, the bot's member events, and the `SyncAllGuilds` job (every 3 hours, one `SyncGuildMembers` per enabled server; run it by hand with `vendor/bin/sail artisan discord:sync-members`).

## Frontend

- Shared prop `auth.user`: `{ id, discord_id, name, avatar_url, is_admin, is_owner } | null` (Typefinder `SharedPageProps`; `@/types` re-exports it as `User`). Owners see **Servers** in the sidebar.
- The `auth/Login` page prop `error` is typed as the `AccessDeniedReason` enum.

## Configuration

```dotenv
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
DISCORD_REDIRECT_URI="${APP_URL}/auth/discord/callback"
DISCORD_OWNER_IDS=        # your Discord user ID(s), comma-separated
DISCORD_BOT_TOKEN=
```

Everything per server (enabled, roles) lives in the `guilds` table and is edited on `/admin/guilds`. Discord app setup: [discord-app.md](discord-app.md).

## Troubleshooting

| Symptom                          | Cause                                                                                                                                                                                                              |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `oauth-failed` straight away     | Wrong client ID or secret, or the redirect URI isn't registered exactly as `DISCORD_REDIRECT_URI`. Also happens when the callback page is reloaded, because the one-time code was already used. Just log in again. |
| `not-member`                     | Not in any **enabled** server. Enable the server on `/admin/guilds` (log in as an owner first).                                                                                                                    |
| `missing-role`                   | In an enabled server, but none of the roles chosen for it.                                                                                                                                                         |
| Can't log in at all as the owner | `DISCORD_OWNER_IDS` must be your user ID (right-click yourself → Copy User ID), not a server or role ID                                                                                                            |
