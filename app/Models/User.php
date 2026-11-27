<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A guild member who has logged in to the website (Discord OAuth only).
 *
 * @property int $id
 * @property int $discord_user_id
 * @property string|null $remember_token
 * @property Carbon|null $last_access_check_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DiscordUser $discordUser
 */
#[Fillable(['discord_user_id', 'last_access_check_at'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function discordUser(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class);
    }

    /**
     * Users have no password; Discord OAuth is the only way in.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_access_check_at' => 'datetime',
        ];
    }
}
