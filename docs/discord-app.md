# Discord application setup (owner tasks)

A single Discord application provides both the **website login** (OAuth2) and the **bot**, which can be in **several of your servers**. You need it from Phase 2 (login) and Phase 3 (bot) on. Everything is done at https://discord.com/developers/applications.

## 1. Create the application

**New Application** → name it, e.g. "Friendly Friend". You can set the bot's name and avatar under **General Information**.

## 2. Installation tab

- **Installation Contexts:** tick **Guild Install** only and untick **User Install**. The bot belongs to your servers, not to people's accounts.
- **Install Link:** **None**. You invite the bot to each server with the URL from step 5.

## 3. OAuth2 tab (website login)

- Copy **Client ID** → `DISCORD_CLIENT_ID`.
- **Reset Secret**, then copy it → `DISCORD_CLIENT_SECRET`. Discord shows it only once.
- **Redirects:** add both of these. They must match `DISCORD_REDIRECT_URI` exactly, including `http`/`https`, port and the missing trailing slash:
    - `http://localhost/auth/discord/callback` (dev; Discord allows plain http for localhost)
    - `https://<your-domain>/auth/discord/callback` (prod)
- The login asks only for the `identify`, `guilds` and `guilds.members.read` scopes. The app sets those itself, so nothing else to configure here.

## 4. Bot tab

- **Reset Token**, then copy it → `DISCORD_BOT_TOKEN`. Treat it like a password: anyone with it controls the bot. If it ever leaks, reset it.
- **Public Bot:** **off**, so only you can add it to servers.
- **Requires OAuth2 Code Grant:** **off**.
- **Privileged Gateway Intents:** turn on all three:
    - **Presence Intent**: game and activity stats
    - **Server Members Intent**: member join, leave and role sync
    - **Message Content Intent**: reading "@bot …" quote messages

    Without them, Discord closes the bot's connection with code `4014`. Bots in fewer than 100 servers don't need verification for these.

## 5. Invite the bot to your servers

**OAuth2 → URL Generator:**

- **Scopes:** `bot` and `applications.commands`
- **Bot permissions:**
    - View Channels
    - Send Messages
    - Send Messages in Threads
    - Embed Links
    - Read Message History (replies, channel imports)
    - Create Events and Manage Events (mirroring events as native Discord events)

Already invited the bot without the event permissions? Give its role **Create Events** and **Manage Events** in Server Settings → Roles. Without them, announcements still work; only the native Discord event is skipped (logged as a warning).

Open the generated URL, pick a server and authorize. Repeat for every server the bot should be in. You need **Manage Server** on that server. If you limit which channels the bot can see, it must be able to read every channel you want stats and quotes from.

## 6. Your user ID (owner)

In Discord: **User Settings → Advanced → Developer Mode** on. Then right-click **yourself** → **Copy User ID** → `DISCORD_OWNER_IDS` (comma-separate several owners).

Servers and roles are **not** configured in `.env`. The bot registers every server it's in, and you enable servers and pick their roles by name on `/admin/guilds` (see [admin.md](admin.md)).

## 7. Fill `.env` and start

```dotenv
DISCORD_CLIENT_ID=123456789012345678
DISCORD_CLIENT_SECRET=...
DISCORD_REDIRECT_URI="${APP_URL}/auth/discord/callback"
DISCORD_OWNER_IDS=123456789012345678
DISCORD_BOT_TOKEN=...
COMPOSE_PROFILES=bot
```

```bash
vendor/bin/sail up -d                       # starts the bot too (COMPOSE_PROFILES=bot)
vendor/bin/sail restart horizon bot         # long-running processes only read .env on start
vendor/bin/sail artisan bot:status
```

Then:

1. Open http://localhost and log in with Discord. As an owner you get in even before any server is enabled.
2. Go to **Servers** in the sidebar, enable your server(s) and choose the roles that may log in and the admin roles.

## 8. AI keys (Phase 4)

At least one of `OPENAI_API_KEY`, `ANTHROPIC_API_KEY` or `GEMINI_API_KEY`. Set two if you want a fallback provider.

## Troubleshooting

| Symptom                                    | Fix                                                                                              |
| ------------------------------------------ | ------------------------------------------------------------------------------------------------ |
| Discord says "Invalid OAuth2 redirect_uri" | The redirect in step 3 doesn't exactly match `DISCORD_REDIRECT_URI`                              |
| Login page says "not a member"             | You're not in any **enabled** server. Enable it on `/admin/guilds`                               |
| Login page says "missing role"             | None of your roles is chosen for that server on `/admin/guilds`                                  |
| A server is missing on `/admin/guilds`     | The bot isn't in it or isn't running (`bot:status`)                                              |
| Bot log: `4004 Authentication failed`      | Wrong or reset `DISCORD_BOT_TOKEN`                                                               |
| Bot log: `4014 Disallowed intents`         | Step 4's privileged intents aren't turned on                                                     |
| `bot:status` offline                       | `vendor/bin/sail logs bot`. Is `COMPOSE_PROFILES=bot` set? Did you restart after editing `.env`? |
