# Admin: servers and settings

`/admin/guilds` (sidebar → **Servers**) is **owners only** (`manage-guilds` gate, `DISCORD_OWNER_IDS`).

The bot registers each server when it joins or connects (`GUILD_CREATE`). A new server shows up here **disabled**. Per server:

| Setting                                             | Effect                                                                                                                                                            |
| --------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Enabled**                                         | The bot records the server's members, quotes and stats. Its members can log in according to the settings below. Turning it on starts a member sync straight away. |
| **Let every member log in**                         | Any member of the server may log in, whatever their roles.                                                                                                        |
| **Roles that may log in**                           | Members with any of these roles may log in.                                                                                                                       |
| **Post saved quotes in**                            | Text channel where the bot posts saved quotes, or "Don't post". The list comes from the bot (channels it can see).                                                |
| **Show /quote random only to the person who asked** | Off by default: the random quote is posted for everyone in the channel. Cached for the bot in `Quotes\PrivateRandomQuotes`.                                       |
| **Admin roles**                                     | Members with these roles may also log in and get the admin tools (Horizon, and later quote moderation). They **can't** manage servers; only owners can.           |

- Role pickers list the server's roles, highest first, excluding `@everyone` and roles managed by integrations or bots. The role list updates by itself when roles change on Discord (`GUILD_ROLE_*` events).
- "Bot not in server" means the bot was removed (`GUILD_DELETE`). The server's data and settings stay. Disable the server to remove access through it.
- Access is checked on every request, so changes apply immediately. Someone who loses their last way in is logged out on their next click.

Code: `App\Http\Controllers\Admin\GuildController`, `App\Http\Requests\Admin\UpdateGuildRequest` (role IDs must be assignable roles of that server), `App\Access\Actions\UpdateGuildSettings`, page `resources/js/pages/admin/Guilds.vue`.

# Admin: people

`/admin/people` (sidebar → **People**) is for admins (`admin` gate: owners and admin roles). People are shared across servers.

- **Nicknames:** add or remove aliases ("Marre" → Martin). They are unique regardless of case. Quote capture uses them to find the right person.
- **Discord account:** link a person to a Discord account, or unlink them ("Not on Discord"). An account can belong to only one person; merge the two people instead.
- **Merge:** `MergePeople` moves a duplicate's quote lines, nicknames and Discord link to another person, keeps the duplicate's name as a nickname, and deletes the duplicate. It can't be undone.

The Servers page also sets **Post saved quotes in**, **Announce events in** and **Watch for posted quotes in** (quotes posted without the bot are picked up for review on Imports, as a standing "Watching" import per channel, or published straight away if **Publish them straight away** is ticked; see [quotes.md](quotes.md)).

## Settings

`/admin/settings` (sidebar → **Settings**) is **owners only** too. It holds the limits that apply to every server:

| Setting                                          | Default    | Bounds |
| ------------------------------------------------ | ---------- | ------ |
| **First reminder** (DM to everyone going)        | 24 hours   | 1–168  |
| **Second reminder** (must come after the first)  | 2 hours    | 1–48   |
| **Nudge people on Maybe** before "answer by"     | 24 hours   | 1–168  |
| **Drafts expire after**                          | 14 minutes | 1–14   |
| **Quotes per top list**                          | 10         | 3–50   |
| **This-or-that rounds before a quote is ranked** | 3          | 1–50   |

A setting nobody has saved is its default, and a stored value is clamped to its bounds when read. Changes apply from the next run of whatever reads them. Drafts can't live past 14 minutes because Discord only lets the bot edit an interaction reply for 15.

Code: `App\Enums\AppSetting` (default, bounds, label and help per setting), `App\Access\AppSettings` (reads, with the fallback and clamp), `App\Access\Actions\UpdateAppSettings`, `App\Http\Controllers\Admin\SettingsController`, table `app_settings`, page `resources/js/pages/admin/Settings.vue`.
