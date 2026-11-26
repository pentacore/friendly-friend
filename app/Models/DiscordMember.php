<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DiscordMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A member of the configured Discord guild, synced from Discord whether or
 * not they ever log in to the website.
 *
 * @property int $id
 * @property string $discord_id
 * @property string $username
 * @property string|null $global_name
 * @property string|null $guild_nick
 * @property string|null $avatar
 * @property string|null $guild_avatar
 * @property list<string> $role_ids
 * @property bool $in_guild
 * @property Carbon|null $tracking_opted_out_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $display_name
 */
#[Fillable(['discord_id', 'username', 'global_name', 'guild_nick', 'avatar', 'guild_avatar', 'role_ids', 'in_guild'])]
class DiscordMember extends Model
{
    /** @use HasFactory<DiscordMemberFactory> */
    use HasFactory;

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * The name shown in the guild: server nickname, then global display name,
     * then username.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->guild_nick ?? $this->global_name ?? $this->username);
    }

    /**
     * Discord CDN URL: guild avatar, then user avatar, then Discord's default
     * avatar (index = (snowflake >> 22) % 6).
     */
    public function avatarUrl(): string
    {
        $cdn = config()->string('discord.cdn_base');
        $guildId = config()->string('discord.guild_id');

        if ($this->guild_avatar !== null && $guildId !== '') {
            return "{$cdn}/guilds/{$guildId}/users/{$this->discord_id}/avatars/{$this->guild_avatar}.png";
        }

        if ($this->avatar !== null) {
            return "{$cdn}/avatars/{$this->discord_id}/{$this->avatar}.png";
        }

        $snowflake = (int) $this->discord_id;
        $index = ($snowflake >> 22) % 6;

        return "{$cdn}/embed/avatars/{$index}.png";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role_ids' => 'array',
            'in_guild' => 'boolean',
            'tracking_opted_out_at' => 'datetime',
        ];
    }
}
