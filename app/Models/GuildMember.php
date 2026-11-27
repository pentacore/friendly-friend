<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GuildMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Discord user's membership of one guild: nickname, roles, still there?
 *
 * @property int $id
 * @property int $guild_id
 * @property int $discord_user_id
 * @property string|null $nick
 * @property string|null $guild_avatar
 * @property list<string> $role_ids
 * @property bool $in_guild
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Guild $guild
 * @property-read DiscordUser $discordUser
 * @property-read string $display_name
 */
#[Fillable(['guild_id', 'discord_user_id', 'nick', 'guild_avatar', 'role_ids', 'in_guild'])]
class GuildMember extends Model
{
    /** @use HasFactory<GuildMemberFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Guild, $this>
     */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function discordUser(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class);
    }

    /**
     * Server nickname, then the account's display name.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->nick ?? $this->discordUser->display_name);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role_ids' => 'array',
            'in_guild' => 'boolean',
        ];
    }
}
