<?php

declare(strict_types=1);

namespace App\Access\Actions;

use App\Access\Exceptions\AccessDeniedException;
use App\Access\GuildAccess;
use App\Discord\Data\GuildMemberData;
use App\Discord\DiscordUserApi;
use App\Enums\AccessDeniedReason;
use App\Models\User;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Turns a completed Discord OAuth login into a site user, if the person is
 * a guild member with an allowed role. Members without access are still
 * recorded (the stats and quotes need them), but get no user.
 */
final readonly class LoginWithDiscord
{
    public function __construct(
        private DiscordUserApi $discord,
        private UpsertDiscordMember $upsertMember,
        private GuildAccess $access,
    ) {}

    /**
     * @throws AccessDeniedException
     */
    public function handle(SocialiteUser $discordUser): User
    {
        $memberData = $this->discord->guildMember($discordUser->token, config()->string('discord.guild_id'));

        if (! $memberData instanceof GuildMemberData) {
            throw new AccessDeniedException(AccessDeniedReason::NotMember);
        }

        $member = $this->upsertMember->handle($memberData);

        if (! $this->access->allows($member)) {
            throw new AccessDeniedException(AccessDeniedReason::MissingRole);
        }

        $user = User::query()->firstOrCreate(['discord_member_id' => $member->id]);
        $user->forceFill(['last_access_check_at' => now()])->save();

        return $user;
    }
}
