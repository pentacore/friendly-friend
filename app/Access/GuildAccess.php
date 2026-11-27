<?php

declare(strict_types=1);

namespace App\Access;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\GuildMember;
use Illuminate\Support\Collection;

/**
 * Who may use the site. Owners (DISCORD_OWNER_IDS) always may. Anyone else
 * needs at least one membership of an *enabled* guild that lets them in,
 * judged by that guild's own settings (allow all members, allowed roles,
 * admin roles).
 */
final readonly class GuildAccess
{
    public function allows(DiscordUser $user): bool
    {
        return $this->isOwner($user)
            || $this->activeMemberships($user)->contains($this->membershipAllows(...));
    }

    public function isAdmin(DiscordUser $user): bool
    {
        return $this->isOwner($user)
            || $this->activeMemberships($user)->contains($this->membershipIsAdmin(...));
    }

    public function isOwner(DiscordUser $user): bool
    {
        return in_array($user->discord_id, $this->ownerIds(), true);
    }

    /**
     * May the user use the bot's features in this particular guild?
     */
    public function allowsInGuild(DiscordUser $user, Guild $guild): bool
    {
        return $guild->enabled
            && ($this->isOwner($user) || in_array($guild->id, $this->accessibleGuildIds($user), true));
    }

    /**
     * Is the user an admin of this particular guild (or an owner)?
     */
    public function isAdminInGuild(DiscordUser $user, Guild $guild): bool
    {
        return $guild->enabled
            && ($this->isOwner($user) || $this->activeMemberships($user)
                ->contains(fn (GuildMember $membership): bool => $membership->guild_id === $guild->id && $this->membershipIsAdmin($membership)));
    }

    /**
     * IDs of the enabled guilds through which the user gets in.
     *
     * @return list<int>
     */
    public function accessibleGuildIds(DiscordUser $user): array
    {
        return array_values($this->activeMemberships($user)
            ->filter($this->membershipAllows(...))
            ->map(fn (GuildMember $membership): int => $membership->guild_id)
            ->all());
    }

    /**
     * Guilds whose content the user may see: those that let them in, or every
     * enabled guild for owners.
     *
     * @return list<int>
     */
    public function visibleGuildIds(DiscordUser $user): array
    {
        if ($this->isOwner($user)) {
            return array_values(Guild::query()->enabled()->get()->map(fn (Guild $guild): int => $guild->id)->all());
        }

        return $this->accessibleGuildIds($user);
    }

    /**
     * Guilds the user moderates: those where they have an admin role, or
     * every enabled guild for owners.
     *
     * @return list<int>
     */
    public function adminGuildIds(DiscordUser $user): array
    {
        if ($this->isOwner($user)) {
            return $this->visibleGuildIds($user);
        }

        return array_values($this->activeMemberships($user)
            ->filter($this->membershipIsAdmin(...))
            ->map(fn (GuildMember $membership): int => $membership->guild_id)
            ->all());
    }

    private function membershipAllows(GuildMember $membership): bool
    {
        $guild = $membership->guild;

        return $guild->allow_all_members
            || $this->hasAnyRole($membership, $guild->allowed_role_ids)
            || $this->membershipIsAdmin($membership);
    }

    private function membershipIsAdmin(GuildMember $membership): bool
    {
        return $this->hasAnyRole($membership, $membership->guild->admin_role_ids);
    }

    /**
     * Memberships the user still holds in enabled guilds.
     *
     * @return Collection<int, GuildMember>
     */
    private function activeMemberships(DiscordUser $user): Collection
    {
        return $user->memberships()
            ->where('in_guild', true)
            ->whereRelation('guild', 'enabled', true)
            ->with('guild')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<string>  $roleIds
     */
    private function hasAnyRole(GuildMember $membership, array $roleIds): bool
    {
        return array_intersect($membership->role_ids, $roleIds) !== [];
    }

    /**
     * @return list<string>
     */
    private function ownerIds(): array
    {
        return array_values(array_filter(config()->array('discord.owner_ids'), is_string(...)));
    }
}
