# Login and access

Login is **Discord OAuth only**. There are no passwords, no registration and no email (Fortify was removed).

## Flow

1. `GET /login` shows the "Log in with Discord" page (`auth/Login`).
2. `GET /auth/discord/redirect` sends the browser to Discord with scopes `identify` and `guilds.members.read` (`DiscordAuthController::redirect`).
3. `GET /auth/discord/callback` runs `App\Access\Actions\LoginWithDiscord`:
    - calls `GET /users/@me/guilds/{DISCORD_GUILD_ID}/member` with the user's token (`App\Discord\DiscordUserApi`)
    - not a member (404) → back to login with `error=not-member`
    - member → `discord_members` row created or updated (`UpsertDiscordMember`). The row is kept even when access is denied, because quotes and stats need every member.
    - no allowed role → `error=missing-role`
    - otherwise a `users` row is found or created and logged in with "remember me"
4. `POST /logout`.

## Who gets in

`App\Access\GuildAccess`:

- `allows($member)`: the member is still in the guild **and** has a role in `DISCORD_ALLOWED_ROLE_IDS`, or an admin role. If `DISCORD_ALLOWED_ROLE_IDS` is empty, every guild member gets in.
- `isAdmin($member)`: in the guild and has a role in `DISCORD_ADMIN_ROLE_IDS`.

The `admin` gate (`AppServiceProvider`) wraps `isAdmin`. Horizon's `viewHorizon` gate uses it (outside `local`).

## Staying in

The `guild.access` middleware (`App\Http\Middleware\EnsureGuildAccess`) runs on every app route together with `auth`. It re-checks `GuildAccess::allows()` against the stored member row. Someone who left the server or lost their role is logged out on their next request and sees `error=access-revoked`.

The member rows are kept current by:

- every login
- the scheduled `SyncGuildMembers` job (every 3 hours, bot token). See below.
- the bot's member events (Phase 3)

## Frontend

- Shared prop `auth.user`: `{ id, discord_id, name, avatar_url, is_admin } | null`, typed by Typefinder (`SharedPageProps`). `@/types` re-exports it as `User`.
- The `auth/Login` page prop `error` is typed as the `AccessDeniedReason` enum (`not-member`, `missing-role`, `access-revoked`, `oauth-failed`). The page maps each value to a message.

## Configuration

`config/discord.php` and `config/services.php` (`discord`), from `.env`:

```dotenv
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
DISCORD_REDIRECT_URI="${APP_URL}/auth/discord/callback"
DISCORD_BOT_TOKEN=
DISCORD_GUILD_ID=
DISCORD_ALLOWED_ROLE_IDS=   # comma-separated; empty = any member
DISCORD_ADMIN_ROLE_IDS=     # comma-separated
```

Setting up the Discord application: [discord-app.md](discord-app.md).

## Troubleshooting

| Symptom                                  | Cause                                                                                                                |
| ---------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| `oauth-failed` straight away             | Wrong client ID or secret, or the redirect URI isn't registered in the Discord app exactly as `DISCORD_REDIRECT_URI` |
| `not-member` though you're on the server | `DISCORD_GUILD_ID` is wrong                                                                                          |
| `missing-role`                           | Your role ID isn't in `DISCORD_ALLOWED_ROLE_IDS` (copy the role ID, not the name)                                    |
| Logged out every few hours               | The member sync saw you without the role. Check your roles and `DISCORD_ALLOWED_ROLE_IDS`                            |
