# Phase 2: Discord Access Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Only members of the configured Discord server who have an allowed role can log in (Discord OAuth only), stay logged in only while they keep access, and admins (by role) get the Horizon dashboard.

**Architecture:** Remove Fortify and all password auth. Socialite (Discord provider) handles the OAuth redirect and callback. After the callback, the user's OAuth token calls `GET /users/@me/guilds/{guild}/member` to read their roles. `discord_members` stores every known member, and `users` links one-to-one to a member. The `GuildAccess` service decides "allowed" and "admin" from config role IDs. The `guild.access` middleware re-checks on every request using the stored member row. `SyncGuildMembers` (scheduled every 3 h, bot token) refreshes member rows, so the middleware logs people out once they lose access. The bot keeps rows fresh in real time from Phase 3.

**Tech Stack:** Laravel 13, laravel/socialite 5.33, socialiteproviders/discord 4.2, Pest 5 (`Socialite::fake`, `Http::fake`), Inertia/Vue.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-11-26-friendly-friend-design.md` §4 (identity tables) and §7 (access).
- Discord IDs (snowflakes) are stored as **strings**, because they exceed JavaScript's safe integer range.
- `DISCORD_ALLOWED_ROLE_IDS` / `DISCORD_ADMIN_ROLE_IDS` are comma-separated. An **empty allowed list means any server member** may log in.
- OAuth scopes: `identify`, `guilds.members.read`. No email is collected.
- All commands go through `vendor/bin/sail`. Every commit updates `.ai/guidelines` and `docs` and runs `boost:update` and `typefinder:generate`.
- Departure from the spec: users who lose access aren't logged out by deleting their sessions (sessions live in Redis). Instead the `guild.access` middleware rejects them on their next request. Update spec §7 to match.

## File map

| File | Responsibility |
|---|---|
| `config/discord.php` | guild_id, allowed_role_ids, admin_role_ids, bot_token, api_base |
| `config/services.php` (`discord`) | OAuth client id, secret, redirect for Socialite |
| `database/migrations/*_create_discord_members_table.php` | `discord_members` |
| `database/migrations/0001_01_01_000000_create_users_table.php` | Reshape `users` (fresh project, edited in place): drop name, email, password; add `discord_member_id` |
| `app/Models/DiscordMember.php` | Model, `avatarUrl()`, `displayName()`, `roleIds` cast to array |
| `app/Models/User.php` | `discordMember()` belongsTo |
| `app/Discord/Data/GuildMemberData.php` | Readonly DTO parsed from Discord's guild-member JSON |
| `app/Discord/DiscordUserApi.php` | `guildMember(string $accessToken, string $guildId): ?GuildMemberData` (OAuth user token) |
| `app/Discord/DiscordBotApi.php` | `guildMembers(string $guildId): iterable<GuildMemberData>` (bot token, paginated) |
| `app/Access/GuildAccess.php` | `allows(DiscordMember): bool`, `isAdmin(DiscordMember): bool` |
| `app/Access/Actions/UpsertDiscordMember.php` | `handle(GuildMemberData, bool $inGuild = true): DiscordMember` |
| `app/Access/Actions/LoginWithDiscord.php` | `handle(SocialiteUser): User`. Throws `AccessDeniedException` |
| `app/Access/Exceptions/AccessDeniedException.php` | Reason enum: `NotMember`, `MissingRole` |
| `app/Access/Jobs/SyncGuildMembers.php` | Bot-token sync; marks members who are missing as `in_guild=false` |
| `app/Http/Middleware/EnsureGuildAccess.php` | Alias `guild.access` |
| `app/Http/Controllers/Auth/DiscordAuthController.php` | `login`, `redirect`, `callback`, `logout` |
| `app/Providers/AppServiceProvider.php` | Socialite Discord registration, `admin` gate |
| `app/Providers/HorizonServiceProvider.php` | `viewHorizon` → `admin` gate |
| `routes/auth.php`, `routes/web.php`, `routes/settings.php`, `routes/console.php` | Routes, schedule |
| `resources/js/pages/auth/Login.vue` | "Log in with Discord" button and denial message |
| `resources/js/types/auth.ts`, `UserInfo.vue`, `UserMenuContent.vue`, `settings/Layout.vue`, `Welcome.vue` | Discord user shape, remove password/profile UI |
| Removed | Fortify (package, provider, config), `Actions/Fortify`, `Concerns/*ValidationRules`, `Http/Requests/Settings/*`, `Settings/ProfileController`, `Settings/SecurityController`, pages `auth/ForgotPassword`, `auth/ResetPassword`, `settings/Profile`, `settings/Security`, `DeleteUser.vue`, `PasswordInput.vue`, related tests |

## Tasks

### Task 1: Config, schema and models
- [ ] Write failing tests in `tests/Unit/Access/GuildAccessTest.php`: allowed when a role overlaps; denied when not in the server; denied when there's no overlapping role; any member allowed when the allowed list is empty; admin by admin role.
- [ ] Add `config/discord.php`, `.env.example` keys and `services.discord`.
- [ ] Migration for `discord_members` (`discord_id` string unique, username, global_name null, guild_nick null, avatar null, guild_avatar null, role_ids jsonb default `[]`, in_guild bool default true, tracking_opted_out_at null, timestamps). Reshape the `users` migration (id, `discord_member_id` FK unique cascade, remember_token, last_access_check_at null, timestamps). Drop `password_reset_tokens`.
- [ ] `DiscordMember` model, factory (states `withRoles(array)`, `leftGuild()`), `User` model and factory (`forMember`). Implement `GuildAccess`. Tests pass.
- [ ] Commit `feat(access): discord member schema and guild access rules`.

### Task 2: Discord API clients and member upsert
- [ ] Failing tests with `Http::fake`. `DiscordUserApi::guildMember` returns DTO data (id, username, global_name, nick, roles, avatar) on 200 and `null` on 404, and throws on any other status. `DiscordBotApi::guildMembers` follows the `after` pagination until fewer than 1000 rows come back. `UpsertDiscordMember` creates and then updates by `discord_id`.
- [ ] Implement, then commit `feat(access): discord api clients and member upsert`.

### Task 3: OAuth login flow
- [ ] Failing feature tests (`tests/Feature/Auth/DiscordLoginTest.php`), using `Socialite::fake('discord', User::fake([...]) with token)` and `Http::fake` for the member endpoint:
  - guest `GET /login` renders `auth/Login`
  - `GET /auth/discord/redirect` redirects to discord.com with the scopes
  - callback, member with role → user created, logged in (remembered), redirect to dashboard
  - callback, not a member (404) → guest, redirect to login with `error` flash "not-member"
  - callback, member without role → guest, `error` "missing-role"
  - a second login reuses the same user and updates username and roles
  - `POST /logout` logs out
- [ ] Implement `LoginWithDiscord`, the controller, `routes/auth.php`, and register the Socialite provider (`Event::listen(SocialiteWasCalled::class, [DiscordExtendSocialite::class, 'handle'])`). Commit `feat(auth): discord oauth login`.

### Task 4: Access middleware, admin gate, Horizon
- [ ] Failing tests: an authenticated user whose member left the server or lost the role → logged out and redirected to login with `error` "access-revoked"; a user with access → 200; the `admin` gate is true only for the admin role; `/horizon` returns 403 for a non-admin in the `production` env (set `app()->detectEnvironment`), 200 for an admin.
- [ ] Implement `EnsureGuildAccess` (alias `guild.access`, applied with `auth` to app routes), the `admin` gate, and the `viewHorizon` gate. Commit `feat(access): enforce guild access on every request; admin gate`.

### Task 5: Remove Fortify and password UI; Discord user UI
- [ ] Remove the Fortify package, provider, config, actions, concerns, requests, controllers, pages, components and tests listed above. Settings keeps only Appearance.
- [ ] Shared props: `auth.user` = `{id, discord_id, name, avatar_url, is_admin} | null`, documented with `@typefinder-return`. Update `types/auth.ts` to re-export from Typefinder's `SharedPageProps`, plus `UserInfo` (no email), `UserMenuContent` (settings → appearance), and the `Login.vue` Discord button with error messages. In `Welcome.vue`, drop register.
- [ ] Run `typefinder:generate`, `npm run build`, `types:check`. Commit `refactor(auth): discord-only ui, remove fortify and password auth`.

### Task 6: Member sync job
- [ ] Failing tests: `SyncGuildMembers` upserts every member returned, marks members who are missing as `in_guild=false`, and does nothing (with a logged warning) when there's no bot token. The schedule registers it every 3 hours (`Schedule::events()` assertion).
- [ ] Implement, schedule in `routes/console.php`, add the `discord:sync-members` command for manual runs. Commit `feat(access): scheduled guild member sync`.

### Task 7: Docs, guidelines, spec note, push
- [ ] `docs/auth.md` (flow, config, troubleshooting), update `docs/discord-app.md` (redirect URI), `.ai/guidelines/domain-rules.md` (access section), `architecture.md` (Access namespace, Fortify removed), spec §7 departure note. `boost:update`. Run every check, commit, push, check CI.
