# Phase 3b: Multiple Servers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** The bot and site work across several Discord servers. People are shared, membership is per server, owners come from `.env`, and per-server access settings are managed on an owners-only admin page.

**Architecture:** Replace `discord_members` with `discord_users` (the person) plus `guild_members` (per-server membership), and add `guilds` holding per-server settings and a role cache. `GuildAccess` evaluates every membership in enabled servers. Login lists the user's servers (`guilds` scope) and reads membership in each enabled one. The bot registers servers on `GUILD_CREATE`/`GUILD_UPDATE`/`GUILD_ROLE_*` and tracks bot removal on `GUILD_DELETE`. Member jobs are scoped to a server and do nothing for disabled servers. The router still never touches the DB.

**Spec:** §1, §4 (identity), §7 (access) and §12 (3b), updated 2026-11-27.

## Global Constraints

- Migrations are edited in place (nothing deployed yet). `migrate:fresh` locally.
- Snowflakes are strings. Every access decision goes through `GuildAccess`.
- Owners: `DISCORD_OWNER_IDS` (user IDs). Removed env: `DISCORD_GUILD_ID`, `DISCORD_ALLOWED_ROLE_IDS`, `DISCORD_ADMIN_ROLE_IDS`.
- Gates: `admin` (owners plus admin roles in enabled servers) and `manage-guilds` (owners only).
- New servers start **disabled**.

## Tasks

1. **Schema and models:** migrations for `guilds`, `discord_users`, `guild_members`, `users`; models `Guild`, `DiscordUser`, `GuildMember`, `User` and factories; `config/discord.php` (`owner_ids`); `GuildData` and `GuildRoleData` DTOs.
2. **Access:** `GuildAccess` (`allows(DiscordUser)`, `isAdmin`, `isOwner`, `allowsIn(GuildMember)`); `DiscordUserApi::guilds()`; `LoginWithDiscord` across servers; middleware; gates; shared prop `is_owner`. Tests rewritten for multiple servers.
3. **Bot:** router branches `GUILD_CREATE` → `RegisterGuild` (+ member sync when enabled); `GUILD_UPDATE`, `GUILD_ROLE_CREATE`/`UPDATE`/`DELETE` → `RefreshGuild`; `GUILD_DELETE` (not `unavailable`) → `MarkGuildLeft`; member events → server-scoped jobs. `SyncGuildMembers(guildId)` and the scheduled `SyncAllGuilds`. `DiscordClient::guild($id)` for refreshing roles.
4. **Admin page:** `/admin/guilds` (`manage-guilds`): list servers with icon, bot presence and member count; update `enabled`, `allow_all_members`, `allowed_role_ids` and `admin_role_ids` (validated against the cached roles). Owners see an "Admin" nav entry. Feature tests plus a type-checked Vue page.
5. **Docs:** `docs/auth.md`, `docs/bot.md`, `docs/discord-app.md`, `docs/admin.md`, guidelines, `.env.example`. Run every check, push, CI.
