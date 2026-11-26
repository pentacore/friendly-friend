# Discord application setup (owner tasks)

Needed from Phase 2 (login) and Phase 3 (bot) on.

1. Go to https://discord.com/developers/applications → **New Application** (e.g. "Friendly Friend").
2. **OAuth2**
    - Copy the **Client ID** and **Client Secret** into `.env` (`DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`).
    - Add the redirect URIs `http://localhost/auth/discord/callback` (dev) and `https://<your-domain>/auth/discord/callback` (prod).
3. **Bot**
    - Reset and copy the **token** into `.env` (`DISCORD_BOT_TOKEN`).
    - Turn on the privileged gateway intents **Server Members**, **Presence** and **Message Content**.
4. **Invite the bot** (OAuth2 → URL Generator): scopes `bot` and `applications.commands`. Bot permissions: View Channels, Read Message History, Send Messages, Embed Links. Open the URL and add the bot to your server.
5. Turn on **Developer Mode** in Discord (User Settings → Advanced), then right-click to copy IDs:
    - Server ID → `DISCORD_GUILD_ID`
    - Role IDs allowed to log in → `DISCORD_ALLOWED_ROLE_IDS` (comma-separated)
    - Admin role IDs → `DISCORD_ADMIN_ROLE_IDS` (comma-separated)
    - The channel where saved quotes are posted → `DISCORD_QUOTES_CHANNEL_ID`
6. AI provider key(s) for quote cleanup (Phase 4): at least one of `OPENAI_API_KEY`, `ANTHROPIC_API_KEY` or `GEMINI_API_KEY`. Add two if you want the fallback provider.
