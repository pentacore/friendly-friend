<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DiscordUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A Discord account, the same person across every server. Per-server data
 * (nickname, roles, still a member?) lives in GuildMember.
 *
 * @property int $id
 * @property string $discord_id
 * @property string $username
 * @property string|null $global_name
 * @property string|null $avatar
 * @property Carbon|null $tracking_opted_out_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $display_name
 */
#[Fillable(['discord_id', 'username', 'global_name', 'avatar'])]
class DiscordUser extends Model
{
    /** @use HasFactory<DiscordUserFactory> */
    use HasFactory;

    /**
     * @return HasMany<GuildMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(GuildMember::class);
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Discord CDN URL of the account avatar, or Discord's default avatar
     * (index = (snowflake >> 22) % 6).
     */
    public function avatarUrl(): string
    {
        $cdn = config()->string('discord.cdn_base');

        if ($this->avatar !== null) {
            return "{$cdn}/avatars/{$this->discord_id}/{$this->avatar}.png";
        }

        $snowflake = (int) $this->discord_id;
        $index = ($snowflake >> 22) % 6;

        return "{$cdn}/embed/avatars/{$index}.png";
    }

    /**
     * How the website shows a person in lists and pickers.
     *
     * @return array{id: int, name: string, avatar_url: string}
     */
    public function toMember(): array
    {
        return ['id' => $this->id, 'name' => $this->display_name, 'avatar_url' => $this->avatarUrl()];
    }

    /**
     * Global display name, then username.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->global_name ?? $this->username);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tracking_opted_out_at' => 'datetime',
        ];
    }
}
