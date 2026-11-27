# Admin: servers

`/admin/guilds` (sidebar → **Servers**) is **owners only** (`manage-guilds` gate, `DISCORD_OWNER_IDS`).

The bot registers each server when it joins or connects (`GUILD_CREATE`). A new server shows up here **disabled**. Per server:

| Setting                     | Effect                                                                                                                                                                         |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Enabled**                 | The bot records the server's members (and, later, quotes and stats). Its members can log in according to the settings below. Turning it on starts a member sync straight away. |
| **Let every member log in** | Any member of the server may log in, whatever their roles.                                                                                                                     |
| **Roles that may log in**   | Members with any of these roles may log in.                                                                                                                                    |
| **Admin roles**             | Members with these roles may also log in and get the admin tools (Horizon, and later quote moderation). They **can't** manage servers; only owners can.                        |

- Role pickers list the server's roles, highest first, excluding `@everyone` and roles managed by integrations or bots. The role list updates by itself when roles change on Discord (`GUILD_ROLE_*` events).
- "Bot not in server" means the bot was removed (`GUILD_DELETE`). The server's data and settings stay. Disable the server to remove access through it.
- Access is checked on every request, so changes apply immediately. Someone who loses their last way in is logged out on their next click.

Code: `App\Http\Controllers\Admin\GuildController`, `App\Http\Requests\Admin\UpdateGuildRequest` (role IDs must be assignable roles of that server), `App\Access\Actions\UpdateGuildSettings`, page `resources/js/pages/admin/Guilds.vue`.
