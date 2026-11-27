<?php

declare(strict_types=1);

namespace App\Access;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * What the current user may see and moderate, computed once per request
 * (cached on the Request) so lists don't re-check access per item.
 */
final readonly class AccessSnapshot
{
    /**
     * @param  list<int>  $guildIds  guilds whose quotes the user may see
     * @param  list<int>  $adminGuildIds  guilds the user moderates
     */
    public function __construct(
        public int $userId,
        public int $discordUserId,
        public bool $isOwner,
        public array $guildIds,
        public array $adminGuildIds,
    ) {}

    public static function for(User $user): self
    {
        $access = app(GuildAccess::class);
        $discordUser = $user->discordUser;

        return new self(
            userId: $user->id,
            discordUserId: $discordUser->id,
            isOwner: $access->isOwner($discordUser),
            guildIds: $access->visibleGuildIds($discordUser),
            adminGuildIds: $access->adminGuildIds($discordUser),
        );
    }

    public static function forRequest(Request $request): ?self
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $snapshot = $request->attributes->get(self::class);

        if (! $snapshot instanceof self || $snapshot->userId !== $user->id) {
            $snapshot = self::for($user);
            $request->attributes->set(self::class, $snapshot);
        }

        return $snapshot;
    }

    public function canSeeGuild(int $guildId): bool
    {
        return in_array($guildId, $this->guildIds, true);
    }

    public function moderates(int $guildId): bool
    {
        return in_array($guildId, $this->adminGuildIds, true);
    }
}
