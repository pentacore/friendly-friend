<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Anyone who can be quoted: a Discord user, or someone outside the servers
 * (no discord_user_id).
 *
 * @property int $id
 * @property string $display_name
 * @property string $slug
 * @property int|null $discord_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DiscordUser|null $discordUser
 * @property-read Collection<int, PersonAlias> $aliases
 * @property-read int|null $quote_count  withCount() of distinct quotes
 */
#[Fillable(['display_name', 'slug', 'discord_user_id'])]
#[Table(name: 'people')]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    /**
     * Create a person with a unique slug derived from the name.
     */
    public static function createNamed(string $displayName, ?int $discordUserId = null): self
    {
        $base = Str::slug($displayName) ?: 'person';
        $slug = $base;

        for ($suffix = 2; self::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return self::query()->create([
            'display_name' => $displayName,
            'slug' => $slug,
            'discord_user_id' => $discordUserId,
        ]);
    }

    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function discordUser(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class);
    }

    /**
     * @return HasMany<PersonAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(PersonAlias::class);
    }

    /**
     * @return HasMany<QuoteLine, $this>
     */
    public function quoteLines(): HasMany
    {
        return $this->hasMany(QuoteLine::class);
    }
}
