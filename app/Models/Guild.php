<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GuildFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A Discord server the bot is (or was) in. Registered by the bot; owners
 * enable it and choose which roles get in on /admin/guilds.
 *
 * @property int $id
 * @property string $discord_id
 * @property string $name
 * @property string|null $icon
 * @property bool $bot_present
 * @property bool $enabled
 * @property bool $allow_all_members
 * @property list<string> $allowed_role_ids
 * @property list<string> $admin_role_ids
 * @property list<array{id: string, name: string, color: int, position: int, managed: bool}> $roles
 * @property list<array{id: string, name: string, position: int}> $channels text channels, for pickers
 * @property string|null $quotes_channel_id
 * @property string|null $events_channel_id
 * @property list<string> $watched_channel_ids channels where posted quotes are picked up for review
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $member_count  withCount() alias of members still in the guild
 */
#[Fillable(['discord_id', 'name', 'icon', 'bot_present', 'enabled', 'allow_all_members', 'allowed_role_ids', 'admin_role_ids', 'roles', 'channels', 'quotes_channel_id', 'events_channel_id', 'watched_channel_ids'])]
class Guild extends Model
{
    /** @use HasFactory<GuildFactory> */
    use HasFactory;

    /**
     * @return HasMany<GuildMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(GuildMember::class);
    }

    public function iconUrl(): ?string
    {
        if ($this->icon === null) {
            return null;
        }

        return config()->string('discord.cdn_base')."/icons/{$this->discord_id}/{$this->icon}.png";
    }

    /**
     * Roles an owner can choose for access: not @everyone (its ID is the
     * guild's ID) and not managed by an integration. Highest role first.
     *
     * @return list<array{id: string, name: string, color: int, position: int, managed: bool}>
     */
    public function assignableRoles(): array
    {
        $roles = array_values(array_filter(
            $this->roles,
            fn (array $role): bool => $role['id'] !== $this->discord_id && ! $role['managed'],
        ));

        usort($roles, fn (array $a, array $b): int => $b['position'] <=> $a['position']);

        return $roles;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function enabled(Builder $query): void
    {
        $query->where('enabled', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bot_present' => 'boolean',
            'enabled' => 'boolean',
            'allow_all_members' => 'boolean',
            'allowed_role_ids' => 'array',
            'admin_role_ids' => 'array',
            'roles' => 'array',
            'channels' => 'array',
            'watched_channel_ids' => 'array',
        ];
    }
}
