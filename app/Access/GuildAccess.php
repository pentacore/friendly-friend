<?php

declare(strict_types=1);

namespace App\Access;

use App\Models\DiscordMember;

/**
 * Decides who may use the site, from the member's guild roles and the role
 * IDs configured in config/discord.php.
 */
final readonly class GuildAccess
{
    public function allows(DiscordMember $member): bool
    {
        if (! $member->in_guild) {
            return false;
        }

        $allowed = $this->roleIds('discord.allowed_role_ids');

        return $allowed === []
            || $this->hasAnyRole($member, $allowed)
            || $this->isAdmin($member);
    }

    public function isAdmin(DiscordMember $member): bool
    {
        return $member->in_guild
            && $this->hasAnyRole($member, $this->roleIds('discord.admin_role_ids'));
    }

    /**
     * @param  list<string>  $roleIds
     */
    private function hasAnyRole(DiscordMember $member, array $roleIds): bool
    {
        return array_intersect($member->role_ids, $roleIds) !== [];
    }

    /**
     * @return list<string>
     */
    private function roleIds(string $key): array
    {
        return array_values(array_filter(config()->array($key), is_string(...)));
    }
}
