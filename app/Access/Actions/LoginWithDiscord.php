<?php

declare(strict_types=1);

namespace App\Access\Actions;

use App\Access\Exceptions\AccessDeniedException;
use App\Access\GuildAccess;
use App\Discord\Data\DiscordUserData;
use App\Discord\Data\GuildMemberData;
use App\Discord\DiscordUserApi;
use App\Enums\AccessDeniedReason;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\GuildMember;
use App\Models\User;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Turns a completed Discord OAuth login into a site user. Reads the user's
 * membership of every enabled guild they are in, so access is judged on
 * fresh roles. Members without access are still recorded (quotes and stats
 * need them) but get no user.
 */
final readonly class LoginWithDiscord
{
    public function __construct(
        private DiscordUserApi $discord,
        private RecordDiscordUser $recordUser,
        private RecordGuildMember $recordMember,
        private GuildAccess $access,
    ) {}

    /**
     * @throws AccessDeniedException
     */
    public function handle(SocialiteUser $socialiteUser): User
    {
        $discordUser = $this->recordUser->handle(DiscordUserData::fromPayload($socialiteUser->getRaw()));

        $foundMembership = $this->refreshMemberships($discordUser, $socialiteUser->token);

        if (! $this->access->allows($discordUser)) {
            throw new AccessDeniedException($foundMembership ? AccessDeniedReason::MissingRole : AccessDeniedReason::NotMember);
        }

        $user = User::query()->firstOrCreate(['discord_user_id' => $discordUser->id]);
        $user->forceFill(['last_access_check_at' => now()])->save();

        return $user;
    }

    /**
     * Refreshes the user's membership of every enabled guild. Returns whether
     * they are a member of at least one.
     */
    private function refreshMemberships(DiscordUser $discordUser, string $token): bool
    {
        $theirGuildIds = $this->discord->guildIds($token);
        $foundMembership = false;

        foreach (Guild::query()->enabled()->get() as $guild) {
            $member = in_array($guild->discord_id, $theirGuildIds, true)
                ? $this->discord->guildMember($token, $guild->discord_id)
                : null;

            if ($member instanceof GuildMemberData && $member->discordId === $discordUser->discord_id) {
                $this->recordMember->handle($guild, $member);
                $foundMembership = true;

                continue;
            }

            GuildMember::query()
                ->whereBelongsTo($guild)
                ->whereBelongsTo($discordUser)
                ->update(['in_guild' => false]);
        }

        return $foundMembership;
    }
}
